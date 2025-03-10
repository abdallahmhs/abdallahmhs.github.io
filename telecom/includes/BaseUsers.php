<?php
class BaseUsers {
    private $file_name;

    public function __construct($file_name) {
        $this->file_name = $file_name;
    }

    public function rewrite_users($data) {
        $file = fopen($this->file_name, 'w');
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
    }

    public function append_user($data) {
        $file = fopen($this->file_name, 'a');
        fputcsv($file, $data);
        fclose($file);
    }

    public function read_users() {
        if (!file_exists($this->file_name)) {
            return [];
        }

        $rows = [];
        if (($handle = fopen($this->file_name, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }
            fclose($handle);
        }
        return $rows;
    }
}
?>