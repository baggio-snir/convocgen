# Convocgen

Generates a big chunky HTML document following a template and a CSV data file.

usage: `php convocgen.php [options]`

options:
    `-h`, `--help`              Display this help
    `-v`, `--verbose`           Enable verbose output
    `-o`, `--output <file>`     Output file (by default, will output in stdout)
    `-t`, `--template <file>`   Template file (by default, will use tpl.php)
    `-d`, `--data <file>`       Data file (by default, will use data.csv)
    `--nohead`                  Disable auto-header reading; file starts at the very first line
    `--ignore <nb>`             Ignore the first <nb> lines; used only if --nohead is used


Will process template file, using each data line from data file,
in order to generate a merge HTML file.


## About template file:
    Template file should be a PHP file with HTML.  
    Only context available within the PHP template
    file will be either `$_` (to indicate current context)
    or `$this` for technical reasons.  
    Be wary this still executes any PHP instruction within this file,
    so **never use unverified templates**.


## About data file:
    Data file __has to be a valid CSV file__.  
    **At the moment, only semicolumn (`;`) separated optionally enclosed by quotes (`"`) are supported.**  
    By default, the first line is used as header,
    and will be check to map the names columns,
    searching for "Prénom" and "Nom de famille".  
    The search is case-insensitive and can use accents or not.  
    If your CSV file has no header line, you can use `--nohead` instead.  
    You can also use `--ignore` to ignore several lines at the top of the file.
    This argument is only used with `--nohead`.


## About output file:
    By default, output is `STDIN`, meaning the standard stream.
    You can then pipe the output to another file,
    or use `--output` to create a HTML output file.  
    If the output is `STDIN` and verbose mode is `ON`,
    you'll get mixed output streams,
    so **be sure to use `--output` if you enable verbose mode**.  
    As your generated file will be opened by an HTML browser,
    be sure to include ressources into your HTML
    (for example, using base64 format images)
    or to output the file into a prepared folder with external resources in it.
