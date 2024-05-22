<?php
/*
* Latin2Utf8
* By Jürgen Müller
*
* Connect to a MySql Database and searches for latin1 / windows-1252 chars
* and replaces them with the utf8 char
* Mainly was written for Wordpress. – Also works correctly with serialized data.
* ->updateTables(false) runs in preview mode – no changes are made to the database
* ->updateTables(true) will update all relevant tables
* Recommended: Backup your Database first!
*
* ============================================================================
* 
* Copyright (C) 2024 by Jürgen Müller
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
* 
*/

class Latin2Utf8 {
    private $replace = [
        "€", "‚", "ƒ", "„", "…", "†", "‡", "ˆ",
        "‰", "Š", "‹", "Œ", "Ž", "‘", "’", "“",
        "”", "•", "–", "—", "˜", "™", "š", "›",
        "œ", "ž", "Ÿ", " ", "¡", "¢", "£", "¤",
        "¥", "¦", "§", "¨", "©", "ª", "«", "¬",
        "­" , "®", "¯", "°", "±", "²", "³", "´",
        "µ", "¶", "·", "¸", "¹", "º", "»", "¼",
        "½", "¾", "¿", "À", "Á", "Â", "Ã", "Ä",
        "Å", "Æ", "Ç", "È", "É", "Ê", "Ë", "Ì",
        "Í", "Î", "Ï", "Ð", "Ñ", "Ò", "Ó", "Ô",
        "Õ", "Ö", "×", "Ø", "Ù", "Ú", "Û", "Ü",
        "Ý", "Þ", "ß", "à", "á", "â", "ã", "ä",
        "å", "æ", "ç", "è", "é", "ê", "ë", "ì", 
        "í", "î", "ï", "ð", "ñ", "ò", "ó", "ô",
        "õ", "ö", "÷", "ø", "ù", "ú", "û", "ü",
        "ý", "þ", "ÿ", "ﬀ", "ﬁ", "ﬂ", "✓", "❤",
        "\u{FE0F}", "\u{2728}"
    ];
    private $search;
    private $maxSearchLength;

    private $hostname, $username, $password, $database;
    private $mysqli;

    private $tables;

    /**
     * Replace latin-1/windows-1252 characters with utf8 chars
     *
     * @param string $database database
     * @param string $username db-username
     * @param string $password db-password
     */
    function __construct($database, $username, $password) {

        $this->hostname = 'localhost';
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        
        $this->mysqli = new mysqli(
            $this->hostname,
            $this->username, 
            $this->password, 
            $this->database
        );

        if ($this->mysqli->connect_errno) {
            echo "Failed to connect to MySQL: " . $this->mysqli->connect_error;
            exit();
        }

        $this->initLatinSearchCharacters();
        $this->maxSearchLength = 8;
        $this->getTables();
    }

    function __destruct() {
        $this->mysqli->close();
    }

    /**
     * Converts all utf8 characters in $this->replace to it's windows-1252/iso-8859-1 pendant and save them in $this->search
     */
    private function initLatinSearchCharacters() {
        $result = [];
        foreach ($this->replace as $char) {
            $bytes = unpack("C*", $char);
            $chars = "";
            foreach ($bytes as $byte) {
                $convert = iconv('WINDOWS-1252', 'UTF-8//IGNORE', chr($byte));
                if ($convert) {
                    $chars .= $convert;
                } else {
                    $secondTry = iconv('ISO-8859-1', 'UTF-8', chr($byte));
                    if ($secondTry) {
                        $chars .= $secondTry;
                    } else {
                        die("Conversion failed!");
                    }
                }  
            }
            $result[] = $chars;
        }
        $this->search = $result;
    }

    /**
     * Returns all Tables from Database with all columns
     */
    private function getTables() {
        $tables = [];
        if ($result = $this->mysqli->query("SHOW TABLES")) {
            $tables = $result->fetch_all(MYSQLI_NUM);
            $result->close();

            foreach ($tables as $key => $value) {
                $result = $this->mysqli->query("SHOW COLUMNS FROM `".$value[0]."`");
                $tables[$key]['columns'] = $result->fetch_all();
                $result->close();
            }
        }
        $this->tables = $tables;
    }

    /**
     * Update all tables 
     *
     * @param boolean $update false = preview mode | true = updates are written to the database
     * @return integer total amount of replaced/previewed characters
     */
    public function updateTables($update = false) {
        $this->printHtmlHeader();
        $total = 0;
        foreach ($this->tables as $table) {
            echo '<h2>'.$table[0].'</h2><br>';
            if ($relevantColumns = $this->getRelevantColumns($table)) {
                $total += $this->updateTableColumns($table, $relevantColumns, $update);
            } else {
                echo 'nothing to do<br>';
            }
            echo '<br><br><br><br>';
        }
        echo '<h3><strong>Total found characters over all tables: ' . $total . '</strong></h3><br><br>';
        $this->printHtmlFooter();
        return $total;
    }

    /**
     * Add search and replace strings for additional changes
     *
     * @param string $search    eg: "test@mail.de"
     * @param string $replace   eg: "new@other.de"
     * @return void
     */
    public function addSearchReplace($search, $replace) {
        if (strlen($search) > $this->maxSearchLength) {
            $this->maxSearchLength = strlen($search);
        }
        $this->search[] = $search;
        $this->replace[] = $replace;
    }

    /**
     * Loops through all rows in a $table
     *
     * @param array $table
     * @param array $relevantColumns
     * @param boolean $update
     * @return integer total replaced chars
     */
    private function updateTableColumns($table, $relevantColumns, $update = false) {
        $total = 0;
        $tablename = $table[0];
        $primary = $this->getPrimaryColumn($table['columns']);
        $sql = $this->getSelectSQL($tablename, $relevantColumns, $primary);
        echo $sql . '<br><br>';

        if ($result = $this->mysqli->query($sql)) {
            while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                $total += $this->updateRow($tablename, $row, $update);
            }
            $result->close();
            echo ($update)
                ? '<strong>Total replaced characters in this table: ' . $total . '</strong>'
                : '<strong>Preview:</strong> Characters found in this table, that will be replaced if run with $update = true: <strong>' . $total . '</strong>'; 
        } else {
            echo 'error on SELECT<br>';
        }
        return $total;
    }

    /**
     * Searches for latin1 or windows 1252 chars and updates row when necessary 
     *
     * @param string $tablename
     * @param array $row
     * @param boolean $update
     * @return integer total replaced chars
     */
    private function updateRow($tablename, $row, $update = false) {
        $total = 0;
        $primary[array_key_first($row)] = array_shift($row);
        $updatedRow = [];

        foreach ($row as $key => $value) {
            if (!is_null($value)) {
                $count = 0;
                $html = "";
                $newValue = $this->stringReplace($value, $count, $html);

                if ($count > 0) {
                    echo $html . '<br><br>';
                    if ($this->isSerialized( $newValue )) {
                        $newValue = $this->checkSerialized($newValue);
                    }
                    $updatedRow[$key] = $newValue;
                    $total += $count;
                }
            }  
        }

        if (count($updatedRow) > 0) {
            if ($update) {  // preview mode till explicitly set $update = true;
                $sql = $this->getUpdateSQL($tablename, $updatedRow, $primary);
                $this->mysqli->query($sql);
            }
        }
        return $total;
    }

    /**
     * Get SELECT sql string
     *
     * @param [string] $tablename
     * @param [array] $columns
     * @param [array] $primary
     * @return string
     */
    private function getSelectSQL($tablename, $columns, $primary) {
        array_unshift($columns, $primary);
        return sprintf(
            'SELECT `%s` FROM `%s`;', 
            implode("`, `", $columns), 
            $tablename
        );
    }

    /**
     * Get UPDATE sql string
     *
     * @param string $tablename The tablename
     * @param array $row       The rows to set/update in Assoc array key = columnname
     * @param array $primary   The primary Key Column
     * @return string
     */
    private function getUpdateSQL($tablename, $row, $primary) {
        $primaryColumnName = array_key_first($primary);
        $set = [];
        foreach ($row as $key => $value) {
            $set[] = sprintf(
                "`%s`='%s'",
                $key,
                $this->mysqli->real_escape_string($value)
            );
        }

        return sprintf(
            'UPDATE `%s` SET %s WHERE `%s`=%s;',
            $tablename,
            implode(", ", $set),
            $primaryColumnName,
            $primary[$primaryColumnName]
        );
    }

    /**
     * Returns Relevant Columns (only varchar char text mediumtext longtext)
     *
     * @param array $table
     * @return array|boolean
     */
    private function getRelevantColumns($table) {
        $columnNames = array_column($table['columns'],0);
        $columnTypes = array_column($table['columns'],1);
        $columns = array_filter($columnNames, function($key) use ($columnTypes) {
            return preg_match('/(.*TEXT.*)|(.*CHAR.*)/i', $columnTypes[$key]);
        }, ARRAY_FILTER_USE_KEY);
        if (count($columns) > 0) {
            return $columns;
        }
        return false;
    }

    /**
     * Returns the name of the Primary Key Column
     *
     * @param array $columns
     * @return string
     */
    private function getPrimaryColumn($columns) {
        $keyNames = array_column($columns,3);
        $primaryKey = array_search('PRI', $keyNames);
        return ($columns[$primaryKey][0]);
    }

    /**
     * WORDPRESS function: Returns true if value is serialized
     *
     * @param string $data
     * @param boolean $strict
     * @return boolean
     */
    private function isSerialized( $data, $strict = true ) {
        // If it isn't a string, it isn't serialized.
        if ( ! is_string( $data ) ) {
            return false;
        }
        $data = trim( $data );
        if ( 'N;' === $data ) {
            return true;
        }
        if ( strlen( $data ) < 4 ) {
            return false;
        }
        if ( ':' !== $data[1] ) {
            return false;
        }
        if ( $strict ) {
            $lastc = substr( $data, -1 );
            if ( ';' !== $lastc && '}' !== $lastc ) {
                return false;
            }
        } else {
            $semicolon = strpos( $data, ';' );
            $brace     = strpos( $data, '}' );
            // Either ; or } must exist.
            if ( false === $semicolon && false === $brace ) {
                return false;
            }
            // But neither must be in the first X characters.
            if ( false !== $semicolon && $semicolon < 3 ) {
                return false;
            }
            if ( false !== $brace && $brace < 4 ) {
                return false;
            }
        }
        $token = $data[0];
        switch ( $token ) {
            case 's':
                if ( $strict ) {
                    if ( '"' !== substr( $data, -2, 1 ) ) {
                        return false;
                    }
                } elseif ( ! str_contains( $data, '"' ) ) {
                    return false;
                }
                // Or else fall through.
            case 'a':
            case 'O':
            case 'E':
                return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';
                return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
        }
        return false;
    }


    private function fixStringLength($matches) {
        $string = $matches[2];
        $right_length = strlen($string); // yes, strlen even for UTF-8 characters, PHP wants the mem size, not the char count
        return 's:' . $right_length . ':"' . $string . '";';
    }

    /**
     * Check Serialized Strings and if there are problems with unserialize – fix them
     *
     * @param string $string
     * @return string
     */
    private function checkSerialized($string) {
        if ( !preg_match('/^[aOs]:/', $string) ) return $string;
        if ( @unserialize($string) !== false ) return $string;
        $string = preg_replace("%\n%", "", $string);
        // doublequote exploding
        $data = preg_replace('%";%', "µµµ", $string);
        $tab = explode("µµµ", $data);
        $new_data = '';
        foreach ($tab as $line) {
            $new_data .= preg_replace_callback('%\bs:(\d+):"(.*)%', [$this, 'fixStringLength'], $line);
        }
        return $new_data;
    }


    /**
     * Replaces all $this->search characters found in $value with $this->replace characters
     * 
     * @param string $value
     * @param integer $count / returns, how many will be replaced
     * @param string $html / returns a html preview of the changes made 
     * @return string
     */
    function stringReplace($value, &$count=0, &$html="") {

        if (is_null($value) || $value === "") return;
        
        $split = preg_split("/(".implode("|", $this->search).")/", $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = array_map(function ($value) use (&$count, &$html) {
            if (strlen($value) <= $this->maxSearchLength) {
                $key = array_search($value, $this->search);
                if (is_numeric($key)) {
                    $count++;
                    $replace = $this->replace[$key];
                    $html .= sprintf('<span class="hl" title="%s"><span>%s</span></span>', $replace, $value);
                    return $replace;
                }
            }
            $html .= htmlspecialchars($value);
            return $value;
        }, $split);

        return implode("", $result);
    }


    function printHtmlHeader() {
        echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Converter Latin2Utf8</title>
    <style>
        .hl {
            display: inline-block;
            color: #FFF;
            cursor: pointer;
        }
        
        .hl span {
            display: inline-block;
            background-color: #FF0000;
        }

        .hl:hover span {
            width: 0;
        }
        
        .hl::after {
            display: inline-block;
            content: attr(title);
            transform-origin: 0% 100%;
            width: 0;
        }
        
        .hl:hover::after {
            background-color: #008000;
            width: auto;
            min-width: 1.5em;
            height: 100%;
            text-align: center;
        }
    </style>
</head>
<body>
';
    }

    function printHtmlFooter() {
        echo '</body></html>';
    }

}
