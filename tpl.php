<?php
?>
<!doctype html>
<html>
    <head>
        <title>Test</title>
        <meta charset="utf-8" />
    </head>
    <body>
        <table>
            <?php foreach($_ as $student) { ?>
                <tr>
                    <th><?php echo $student['student']['last'].' '.$student['student']['first']; ?></th>
                    <?php foreach($student['dates'] as $date) { ?>
                        <td>
                            <?php echo $date; ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </table>
    </body>
</html>
