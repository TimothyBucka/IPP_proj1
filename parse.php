<?php

include "errors.php";
include "XMLgenerator.php";

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
            exit(errors::$error_codes["success"]);
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
    private static $op_codes = [
        "MOVE" => ["var", "symb"],
        "CREATEFRAME" => [],
        "PUSHFRAME" => [],
        "POPFRAME" => [],
        "DEFVAR" => ["var"],
        "CALL" => ["label"],
        "RETURN" => [],
        "PUSHS" => ["symb"],
        "POPS" => ["var"],
        "ADD" => ["var", "symb", "symb"],
        "SUB" => ["var", "symb", "symb"],
        "MUL" => ["var", "symb", "symb"],
        "IDIV" => ["var", "symb", "symb"],
        "LT" => ["var", "symb", "symb"],
        "GT" => ["var", "symb", "symb"],
        "EQ" => ["var", "symb", "symb"],
        "AND" => ["var", "symb", "symb"],
        "OR" => ["var", "symb", "symb"],
        "NOT" => ["var", "symb"],
        "INT2CHAR" => ["var", "symb"],
        "STRI2INT" => ["var", "symb", "symb"],
        "READ" => ["var", "type"],
        "WRITE" => ["symb"],
        "CONCAT" => ["var", "symb", "symb"],
        "STRLEN" => ["var", "symb"],
        "GETCHAR" => ["var", "symb", "symb"],
        "SETCHAR" => ["var", "symb", "symb"],
        "TYPE" => ["var", "symb"],
        "LABEL" => ["label"],
        "JUMP" => ["label"],
        "JUMPIFEQ" => ["label", "symb", "symb"],
        "JUMPIFNEQ" => ["label", "symb", "symb"],
        "EXIT" => ["symb"],
        "DPRINT" => ["symb"],
        "BREAK" => []
    ];
    public static $nof_lines = 0;
    public static $nof_comments = 0;
    private static $language = "IPPcode23";

    function __construct($lang) {
        self::$language = $lang;
    }

    private static function var($op) {
        if (preg_match("/^(GF|LF|TF)@[a-zA-Z_\-$&%*!?][0-9a-zA-z_\-$&%*!?]*$/", $op)) {
            return ["var", str_replace("&", "&amp;", $op)];
        }
        return false;
    }

    private static function symb($op) {
        $var = self::var($op);
        if ($var) {
            return $var;
        } else if (
            !preg_match("/^int@(-|\+)?\d+$/", $op) &&
            !preg_match("/^bool@(true|false)$/", $op) &&
            !preg_match("/^string@(\\\\\d{3}|[^\\x00-\\x20\\x23\\x5C])*$/u", $op) &&
            !preg_match("/^nil@nil$/", $op)
        ) {
            echo $op;
            return false;
        } 

        $cut = strpos($op, "@");
        $op1 = substr($op, 0, $cut);
        $op2 = substr($op, $cut+1);

        return [$op1, strtr($op2, ["<" => "&lt;", ">" => "&gt;", "&" => "&amp;"])];
    }

    private static function label($op) {
        if (!preg_match("/^[a-zA-Z_\-$&%*!?][0-9a-zA-z_\-$&%*!?]*$/", $op)) {
            return false;
        }
        return ["label", str_replace("&", "&amp;", $op)];
    }

    private static function type($op) {
        if ($op !== "int" && $op !== "string" && $op !== "bool") {
            return false;
        }
        return ["type", $op];
    }

    private static function instruction($line, $xml) {
        $line = preg_split("/\s+/", $line);
        $in_op_code = strtoupper($line[0]);

        if (!array_key_exists($in_op_code, self::$op_codes)) {
            exit(errors::$error_codes["op_code"]);
        }

        $op_types = self::$op_codes[$in_op_code];
        if (count($op_types) !== count($line)-1) {
            exit(errors::$error_codes["lex_syx"]);
        }
        if (count($op_types) > 0) {
            $xml->instruction_start(self::$nof_lines, $in_op_code);
            foreach (range(0, count($op_types)-1) as $index) {
                $vals = call_user_func("self::".$op_types[$index], $line[$index+1]);
                if (!$vals) {
                    exit(errors::$error_codes["lex_syx"]);
                }
                $xml->arg($index+1, $vals[0], $vals[1]);
            }
            $xml->instruction_end();
        } else {
            $xml->instruction_empty(self::$nof_lines, $in_op_code);
        }
    }

    private static function trim_line($line) {
        $comment = strpos($line, "#");
        if ($comment !== false) {
            self::$nof_comments++;
            $line = substr($line, 0, $comment);
        }
        $line = trim($line);
        return $line;
    }

    public static function parse() {
        $xml = new XMLgen(self::$language);
        $header = false;

        while (($line = fgets(STDIN)) !== false) {
            $line = self::trim_line($line);
            if ($line !== "") {
                if (!$header && strcasecmp($line, ".".self::$language) === 0) {
                    $xml->program_start(self::$language);
                    $header = true;
                } else if (!$header) {        // no header at the start and not empty line
                    exit(errors::$error_codes["header"]);
                } else {
                    self::$nof_lines++;
                    self::instruction($line, $xml);
                }
            }
        }
        $xml->program_end();
    }
}

program::init();
program::run();

?>
