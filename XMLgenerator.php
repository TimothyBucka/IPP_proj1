<?php

class XMLgen {
    function __construct($lang) {
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    }

    public function program_start($lang) {
        echo "<program language=\"".$lang."\">\n";
    }

    public function program_end() {
        echo "</program>\n";
    }

    public function instruction_empty($order, $opcode) {
        echo "\t<instruction order=\"".$order."\" opcode=\"".$opcode."\"/>\n";
    }

    public function instruction_start($order, $opcode) {
        echo "\t<instruction order=\"".$order."\" opcode=\"".$opcode."\">\n";
    }
    
    public function instruction_end() {
        echo "\t</instruction>\n";
    }

    public function arg($order, $type, $value) {
        echo "\t\t<arg".$order." type=\"".$type."\">".$value."</arg".$order.">\n";
    }
}

?>