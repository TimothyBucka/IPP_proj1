<?php

include "errors.php";
include "XMLgenerator.php";

ini_set('display_errors', 'stderr');

class program {
    public static parser $parser;
    private static $supported_args = ["help", "stats", "loc", "comments", "labels", "jumps", "fwjumps", "backjumps", "badjumps", "frequent", "print", "eol"];
    private static $args = array();
    private static $nof_args = 0;
    private static $print_stats = false;
    private static $args_by_file = [];

    private static function print_help() {
        echo "Usage: php/php8 parse.php [--help] [--stats=<file>] [--loc] [--comments] [--labels] [--jumps] [--fwjumps] [--backjumps] [--badjumps] [--frequent] [--print=string] [--eol]\n";
    }

    private static function args() {
        global $argv;
        
        unset($argv[0]);
        foreach ($argv as $item) {
            $item = preg_replace('/^-/', '', $item);
            $item = preg_replace('/^-/', '', $item);
            $value_pair = explode("=", $item, 2);

            if (in_array($value_pair[0], self::$supported_args) === false) {
                exit(errors::$error_codes["params"]);
            }

            if (count($value_pair) === 2) {
                if ($value_pair[0] !== "stats" && $value_pair[0] !== "print") {
                    exit(errors::$error_codes["params"]);
                } else if ($value_pair[0] === "stats" && $value_pair[1] === "") {
                    exit(errors::$error_codes["params"]);
                }
            } else if (count($value_pair) === 1 && ($value_pair[0] === "stats" || $value_pair[0] === "print")){
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

        if (self::$nof_args == 0) {
            return;
        }

        self::$print_stats = true;

        $current_file = "";
        foreach (self::$args as $arg) {
            if (preg_match("/stats=/", $arg)) {
                $current_file = str_replace("stats=", "", $arg);
                if (array_key_exists($current_file, self::$args_by_file)) {
                    exit(errors::$error_codes["file_out"]);
                }
                self::$args_by_file += [$current_file => array()];
            } else {
                if ($current_file === "") {
                    exit(errors::$error_codes["params"]);
                }
                
                if (preg_match("/print=/", $arg)) {
                    $arg = explode("=", $arg, 2);
                } else {
                    $arg = [$arg, ""];
                }

                self::$args_by_file[$current_file][] = $arg;
            }
        }
    }

    private static function stats() {
        if (!self::$print_stats) {
            return;
        }

        // go through all files and print what you need to print
        foreach (self::$args_by_file as $file => $args) {
            $handle = fopen($file, "w");
            if ($handle === false) {
                exit(errors::$error_codes["file_out"]);
            }
            // go through all arguments for current file
            foreach ($args as $arg) {
                switch ($arg[0]) {
                    case "loc":
                        $str = self::$parser->nof_lines."\n";
                        break;
                    case "comments":
                        $str = self::$parser->nof_comments."\n";
                        break;
                    case "labels":
                        $str = self::$parser->labels."\n";
                        break;
                    case "jumps":
                        $str = self::$parser->jumps."\n";
                        break;
                    case "fwjumps":
                        $str = self::$parser->fw_jumps."\n";
                        break;
                    case "backjumps":
                        $str = self::$parser->back_jumps."\n";
                        break;
                    case "badjumps":
                        $str = self::$parser->bad_jumps."\n";
                        break;
                    case "frequent":
                        arsort(self::$parser->frequent);
                        foreach (self::$parser->frequent as $key => $value) {
                            if ($value === 0) {
                                break;
                            }
                            $sorted[] = $key;
                        }
                        $str = implode(",", $sorted)."\n";
                        break;
                    case "print":
                        $str = $arg[1];
                        break;
                    case "eol":
                        $str = "\n";
                        break;
                }
                fwrite($handle, $str);
            }
        }
    }

    public static function init() {
        self::args();
        self::$parser = new parser("IPPcode23");
    }

    public static function run() {
        self::$parser->parse();
        self::stats();
    }
}

class parser {
    private $language = "IPPcode23";
    private $op_codes = [
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
    public  $nof_lines = 0,  // --loc
            $nof_comments = 0, // --comments
            $labels = 0, // --labels
            $jumps = 0,  // --jumps
            $fw_jumps = 0,
            $back_jumps = 0,
            $bad_jumps = 0,
            $frequent = [
                "MOVE" => 0,
                "CREATEFRAME" => 0,
                "PUSHFRAME" => 0,
                "POPFRAME" => 0,
                "DEFVAR" => 0,
                "CALL" => 0,
                "RETURN" => 0,
                "PUSHS" => 0,
                "POPS" => 0,
                "ADD" => 0,
                "SUB" => 0,
                "MUL" => 0,
                "IDIV" => 0,
                "LT" => 0,
                "GT" => 0,
                "EQ" => 0,
                "AND" => 0,
                "OR" => 0,
                "NOT" => 0,
                "INT2CHAR" => 0,
                "STRI2INT" => 0,
                "READ" => 0,
                "WRITE" => 0,
                "CONCAT" => 0,
                "STRLEN" => 0,
                "GETCHAR" => 0,
                "SETCHAR" => 0,
                "TYPE" => 0,
                "LABEL" => 0,
                "JUMP" => 0,
                "JUMPIFEQ" => 0,
                "JUMPIFNEQ" => 0,
                "EXIT" => 0,
                "DPRINT" => 0,
                "BREAK" => 0
            ];
    private $label_arr = array();
    private $not_found_labels = array();

    function __construct($lang) {
        $this->language = $lang;
    }

    private function resolve_bad_fw_jumps() {
        foreach ($this->not_found_labels as $label) {
            if (in_array($label, $this->label_arr)) {
                $this->fw_jumps++;
            } else {
                $this->bad_jumps++;
            }
        }
    }

    private function var($op) {
        if (preg_match("/^(GF|LF|TF)@[a-zA-Z_\-$&%*!?][0-9a-zA-z_\-$&%*!?]*$/", $op)) {
            return ["var", str_replace("&", "&amp;", $op)];
        }
        return false;
    }

    private function symb($op) {
        $var = $this->var($op);
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

    private function label($op) {
        if (!preg_match("/^[a-zA-Z_\-$&%*!?][0-9a-zA-z_\-$&%*!?]*$/", $op)) {
            return false;
        }

        return ["label", str_replace("&", "&amp;", $op)];
    }

    private function type($op) {
        if ($op !== "int" && $op !== "string" && $op !== "bool") {
            return false;
        }
        return ["type", $op];
    }

    private function instruction($line, $xml) {
        $line = preg_split("/\s+/", $line);
        $in_op_code = strtoupper($line[0]);

        if (!array_key_exists($in_op_code, $this->op_codes)) {
            exit(errors::$error_codes["op_code"]);
        }

        $op_types = $this->op_codes[$in_op_code];
        if (count($op_types) !== count($line)-1) {  // wrong amount of arguments
            exit(errors::$error_codes["lex_syx"]);
        }
        if (count($op_types) > 0) {
            $xml->instruction_start($this->nof_lines, $in_op_code);
            foreach (range(0, count($op_types)-1) as $index) {
                $vals = call_user_func(array($this, $op_types[$index]), $line[$index+1]); // call $this->function_I_want($line[$index+1])
                if (!$vals) {
                    exit(errors::$error_codes["lex_syx"]);
                }
                $xml->arg($index+1, $vals[0], $vals[1]);
            }
            $xml->instruction_end();
        } else {
            $xml->instruction_empty($this->nof_lines, $in_op_code);
        }

        // count labels
        if ($line[0] === "LABEL") {
            if (in_array($line[1], $this->label_arr)) { // label already exists
                exit(errors::$error_codes["semantic"]);
            } else {
                $this->labels++;
                $this->label_arr[] = $line[1];
            }
        };

        // count jumps and backjumps
        switch ($line[0]) {
            case "CALL":
            case "JUMP":
            case "JUMPIFEQ":
            case "JUMPIFNEQ":
                $this->jumps++;
                if (!in_array($line[1], $this->label_arr)) {
                    $this->not_found_labels[] = $line[1];
                } else {
                    $this->back_jumps++;
                }
                break;
            case "RETURN":
            case "EXIT":
                $this->jumps++;
                break;
        }

        $this->frequent[$line[0]]++;
    }

    private function trim_line($line) {
        $comment = strpos($line, "#");
        if ($comment !== false) {
            $this->nof_comments++;
            $line = substr($line, 0, $comment);
        }
        $line = trim($line);
        return $line;
    }

    public function parse() {
        $xml = new XMLgen($this->language);
        $header = false;

        while (($line = fgets(STDIN)) !== false) {
            $line = $this->trim_line($line);
            if ($line !== "") {
                if (!$header && strcasecmp($line, ".".$this->language) === 0) {
                    $xml->program_start($this->language);
                    $header = true;
                } else if (!$header) {        // no header at the start and not empty line
                    exit(errors::$error_codes["header"]);
                } else {
                    $this->nof_lines++;
                    $this->instruction($line, $xml);
                }
            }
        }
        $xml->program_end();

        $this->resolve_bad_fw_jumps();
    }
}

program::init();
program::run();

?>
