<?php

class errors {
    public static $error_codes = [
        "success" => 0,   // success
    
        "params" => 10,   // missing or wrong script parameters
        "file_in" => 11,  // input file error
        "file_out" => 12, // output file error
    
        "header" => 21,   // wrong header
        "op_code" => 22,  // wrong operation code
        "lex_syx" => 23,  // lexical or syntax error
    
        "internal" => 99 // internal error
    ];
}

?>