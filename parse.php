<?php

include "errors.php";

ini_set('display_errors', 'stderr');

class program {
    public static parser $parser;
    private static $supported_args = ["help", "stats", "loc", "comments", "labels", "jumps", "fwjumps", "backjumps", "badjumps", "frequent", "print", "eol"];
    private static $args = array();
    private static $nof_args = 0;

    private static function print_help() {
        echo "Usage: php/php8 parse.php [--help] [--stats=<file>] [--loc] [--comments] [--labels] [--jumps] [--fwjumps] [--backjumps] [--badjumps] [--frequent] [--print=string] [--eol]\n";
    }

    private static function args() {
        global $argv;
        
        unset($argv[0]);
        foreach ($argv as $item) {
            $item = preg_replace('/^-/', '', $item);
            $item = preg_replace('/^-/', '', $item);
            $value_pair = explode("=", $item);
            if (in_array($value_pair[0], self::$supported_args) === false || count($value_pair) > 2) {
                exit(errors::$error_codes["params"]);
            }
            if (count($value_pair) === 2 && $value_pair[0] !== "stats" && $value_pair[0] !== "print") {
                exit(errors::$error_codes["params"]);
            }
            if (count($value_pair) === 1 && ($value_pair[0] === "stats" || $value_pair[0] === "print")){
                exit(errors::$error_codes["params"]);
            }
            self::$args[] = $item;
        }
        self::$nof_args = count(self::$args);

        if (in_array("help", self::$args)) {
            if (self::$nof_args > 1) {
                exit(errors::$error_codes["params"]);
            }
            self::print_help();
        }
    }

    public static function init() {
        self::args();
        self::$parser = new parser("IPPcode23");
    }

    public static function run() {
        self::$parser->parse();
    }
}

class parser {
    public static $nof_lines = 0;
    public static $nof_comments = 0;
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

    private static function instruction($line) {
        
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
                    self::instruction($line);
                    self::$nof_lines++;
                }
            }
        }
    }
}

program::init();
program::run();

?>
