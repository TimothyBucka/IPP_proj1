<?php

include "errors.php";

ini_set('display_errors', 'stderr');

class parser {
    private static $nof_lines = 0;
    private static $nof_comments = 0;
    private static $language = "IPPcode23";
    private static $header = false;

    function __construct($lang) {
        self::$language = $lang;
    }

    private static function trim_line($line) {
        $line = trim($line);
        $comment = strpos($line, "#");
        if ($comment !== false) {
            self::$nof_comments++;
            $line = substr($line, 0, $comment);
        }
        return $line;
    }

    public static function parse() {
        while (($line = fgets(STDIN)) !== false) {
            $line = self::trim_line($line);
            if ($line !== "") {
                if (!self::$header && strcasecmp($line, ".".self::$language) === 0) {
                    self::$header = true;
                } else if (!self::$header) {        // no header at the start and not empty line
                    exit(errors::$error_codes["header"]);
                } else {
                    self::$nof_lines++;
                }
            }
        }
        print("Lines: ".self::$nof_lines."\n");
        print("Comments: ".self::$nof_comments."\n");
    }
}

parser::parse();

?>
