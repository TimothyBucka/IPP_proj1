<?php

class XMLgen {
    function __construct($lang) {
        echo '<?xml version="1.0" encoding="UTF-8"?>';
    }

    public function program_start($lang) {
        echo '<program language="'.$lang.'">';
    }

    public function program_end() {
        echo '</program>';
    }

    public function instruction_start($order, $opcode) {
        echo '\t<instruction order="'.$order.'" opcode="'.$opcode.'">';
    }
    
    public function instruction_end() {
        echo '\t</instruction>';
    }

    public function arg($order, $type, $value) {
        echo '\t\t<arg'.$order.' type="'.$type.'">'.$value.'</arg'.$order.'>';
    }
}

?>