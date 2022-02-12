<?php
if(empty($argv) || empty($argc) || ('cli' !== PHP_SAPI)) {
    http_response_code(404);
    exit;
}

class Gen {
    const DEFAULT_OUTPUT = 'php://stdout';
    const DEFAULT_TPL = 'tpl.php';
    const DEFAULT_CSV = 'data.csv';
    
    protected static ?Gen $singleton = null;
    public static function getInstance() {
        if(null === static::$singleton) {
            static::$singleton = new Gen();
        }
        return static::$singleton;
    }
    
    protected bool $verbose = false;
    protected string $outputPath = self::DEFAULT_OUTPUT;
    protected string $tplPath = self::DEFAULT_TPL;
    protected string $csvPath = self::DEFAULT_CSV;
    protected bool $withHeader = true;
    protected int $ignoreFirsts = 0;
    protected ?string $tpl = null;
    protected $csv = null;
    protected function __construct() {
        
    }
    
    protected function debug(string $str): self {
        if($this->verbose) {
            fwrite(STDOUT, $str.PHP_EOL);
        }
        return $this;
    }
    
    protected function error(string $str): self {
        fwrite(STDERR, $str);
        return $this;
    }
    
    protected function parseArguments(array $argv): array {
        $allowedOptions = [
            's' => [
                'h' => 'help',
                'v' => 'verb',
                'o:' => 'out',
                't:' => 'tpl',
                'd:' => 'csv',
            ],
            'l' => [
                'help' => 'help',
                'verbose' => 'verb',
                'output:' => 'out',
                'template:' => 'tpl',
                'data:' => 'csv',
                'nohead' => 'nohead',
                'ignore:' => 'ignore',
            ],
        ];
        $allAllowedOptions = [];
        foreach($allowedOptions['s'] + $allowedOptions['l'] as $k => $v) {
            $allAllowedOptions[str_replace(':', '', $k)] = $v;
        }
        unset($k,$v);

        $args = [
            'verb' => false, // by default
        ];
        $restIndex = null;
        $a = getopt(implode('', array_keys($allowedOptions['s'])), array_keys($allowedOptions['l']), $restIndex);
        $additionalArgs = array_slice($argv, $restIndex);
        foreach($a as $k => $v) {
            $args[$allAllowedOptions[$k]] = is_string($v)? $v:true;
        }
        
        $this->verbose = !!$args['verb'];
        if(!empty($args['out'])) {
            $this->outputPath = $args['out'];
        }
        if(!empty($args['tpl'])) {
            $this->tplPath = $args['tpl'];
        }
        if(!empty($args['csv'])) {
            $this->csvPath = $args['csv'];
        }
        if(!empty($args['nohead'])) {
            $this->withHeader = false;
            if(!empty($args['ignore'])) {
                $this->ignoreFirsts = intval($args['ignore']);
            }
        }
        
        if(!empty($additionalArgs)) {
            $this->debug('Additional args ['.implode(', ', array_keys($additionalArgs)).'] ignored');
        }
        
        $args['help'] = array_key_exists('help', $args);
        
        return $args;
    }
    
    public function execute(array $argv): bool {
        $returns = false;
        $args = $this->parseArguments($argv);
        if($args['help']) {
            $this->help();
        } elseif($this->load()) {
            // we have template and data, let's go
            $returns = $this->merge();
        }
        return $returns;
    }
    
    public function help() {
        echo <<<'EOT'
Generates a big chunky HTML document following a template and a CSV data file.

usage: php convocgen.php [options]

options:
    -h, --help              Display this help
    -v, --verbose           Enable verbose output
    -o, --output <file>     Output file (by default, will output in stdout)
    -t, --template <file>   Template file (by default, will use tpl.php)
    -d, --data <file>       Data file (by default, will use data.csv)
    --nohead                Disable auto-header reading; file starts at the very first line
    --ignore <nb>           Ignore the first <nb> lines; used only if --nohead is used

Will process template file, using each data line from data file, in order to generate a merge HTML file.

About template file:
    Template file has to be a PHP file with HTML.
    Only context available within the PHP template file will be either $_ (to indicate current context) or $this for technical reasons.
    Be wary this still executes any PHP instruction within this file, so never use unverified templates.

About data file:
    Data file has to be a valid CSV file.
    At the moment, only semicolumn (;) separated optionally enclosed by quotes (") are supported.
    By default, the first line is used as header, and will be check to map the names columns, searching for "Prénom" and "Nom de famille".
    The search is case-insensitive and can use accents or not.
    If your CSV file has no header line, you can use --nohead instead.
    You can also use --ignore to ignore several lines at the top of the file. This argument is only used with --nohead.

About output file:
    By default, output is STDIN, meaning the standard stream. You can then pipe the output to another file, or use --output to create a HTML output file.
    If the output is STDIN and verbose mode is ON, you'll get mixed output streams, so be sure to use --output if you enable verbose mode.
    As your generated file will be opened by an HTML browser, be sure to include ressources into your HTML (for example, using base64 format images) or to output the file into a prepared folder with external resources in it.
EOT;
    }
    
    protected function loadTemplate(): bool {
        $returns = false;
        $this->debug('Loading template "'.$this->tplPath.'"...');
        if(file_exists($this->tplPath)) {
            $this->tpl = file_get_contents($this->tplPath);
            if(empty($this->tpl)) {
                $this->error('Template file "'.$this->tplPath.'" is empty or not readable');
            } else {
                $this->debug('Template loaded');
                $returns = true;
            }
        } else {
            $this->error('Template file "'.$this->tplPath.'" does not exist');
        }
        return $returns;
    }
    
    protected function loadData(): bool {
        $returns = false;
        $this->debug('Loading CSV data "'.$this->csvPath.'"...');
        if(file_exists($this->csvPath)) {
            $fh = fopen($this->csvPath, 'r');
            if(false !== $fh) {
                $this->debug('Reading CSV data file...');
                $this->csv = [];
                $keyMap = [
                    'last' => 0,
                    'first' => 1,
                ];
                if($this->withHeader) { // prepare keys, using first line
                    $this->debug('Depopping first line for headers...');
                    if(false !== ($line = fgetcsv($fh, null, ';', '"'))) {
                        $this->debug('Searching for headers tokens...');
                        foreach($line as $k => $v) {
                            $v = trim($v);
                            if((0 === stripos($v, 'pr')) // we cannot use "str_starts_with" as it's case sensitive
                                    && str_ends_with($v, 'nom')) {
                                $this->debug('Found column for "first" name at #'.$k);
                                $keyMap['first'] = $k;
                            } elseif((0 === stripos($v, 'nom')) // we cannot use "str_starts_with" as it's case sensitive
                                    || str_contains($v, 'famille')) {
                                $this->debug('Found column for "last" name at #'.$k);
                                $keyMap['last'] = $k;
                            } else {
                                $this->debug('No scheme found for column #'.$k.' ('.$v.')');
                            }
                        }
                    }
                } elseif($this->ignoreFirsts) {
                    $this->debug('Ignoring first '.$this->ignoreFirsts.' lines...');
                    for($i = 0; $i<$this->ignoreFirsts; $i++) { // pop first lines
                        fgetcsv($fh, null, ';', '"');
                    }
                }
                while(false !== ($line = fgetcsv($fh, null, ';', '"'))) {
                    $this->debug('Reading line...');
                    $this->csv[] = $this->formatDataline($line, $keyMap);
                }
                if(empty($this->csv)) {
                    $this->error('CSV data file "'.$this->tplPath.'" is empty once parsed (invalid or empty csv ?)');
                } else {
                    $this->debug('Formatted CSV data = '.var_export($this->csv, true));
                    $returns = true;
                }
            } else {
                $this->error('CSV data file "'.$this->csvPath.'" is not readable');
            }
        } else {
            $this->error('CSV data file "'.$this->csvPath.'" does not exist');
        }
        return $returns;
    }
    
    protected function formatDataline(array $line, array $keyMap): array {
        return [
            'student' => [
                'first' => $line[$keyMap['first']],
                'last' => $line[$keyMap['last']],
            ],
            'dates' => array_slice($line, 1 + max($keyMap)),
        ];
    }
    
    protected function load(): bool {
        $this->debug('Loading...');
        return $this->loadTemplate() && $this->loadData();
    }
    
    protected function merge(): bool {
        $returns = false;
        $this->debug('Start merging, opening output path "'.$this->outputPath.'"...');
        // don't w+ on streams : it won't end well.
        $fh = fopen($this->outputPath, 'w'.(str_starts_with($this->outputPath, 'php://')? '':'+'));
        if(false !== $fh) {
            $r = $this->mergeDocument($this->csv);
            $this->debug('Writing to output...');
            fwrite($fh, $r);
            fclose($fh);
            $this->debug('Output done and stream closed');
        } else {
            $this->error('Error while opening output file "'.$this->outputPath.'"');
        }
        return $returns;
    }
    
    protected function mergeDocument($_): string {
        $this->debug('Merge document, buffering and including...');
        ob_start();
        include $this->tplPath;
        $r = ob_get_contents();
        ob_end_clean();
        $this->debug('Document merged, buffer stopped and cleant');
        return $r;
    }
}

Gen::getInstance()->execute($argv);
