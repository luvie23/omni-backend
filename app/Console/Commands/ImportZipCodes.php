<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZipCode;

class ImportZipCodes extends Command
{
    protected $signature = 'zipcodes:import {file}';
    protected $description = 'Import ZIP codes from CSV';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error('File not found.');
            return;
        }

        $handle = fopen($file, 'r');

        $header = fgetcsv($handle);

        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {

            ZipCode::updateOrCreate(
                ['zip' => $row[0]],
                [
                    'city' => $row[1],
                    'state' => $row[2],
                    'latitude' => $row[3],
                    'longitude' => $row[4],
                ]
            );

            $count++;
        }

        fclose($handle);

        $this->info("Imported {$count} ZIP codes.");
    }
}
