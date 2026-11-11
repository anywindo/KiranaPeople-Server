<?php

namespace App\Exports;

use App\Models\Location;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class EmployeeTemplateExport implements FromArray, WithEvents, WithHeadings
{
    public function array(): array
    {
        $location = Location::first();

        return [
            ['EMP-00123', '1234567890123456', '1995-07-12', 'John Doe', 'john@example.com', '08123456789', '', $location ? $location->name : ''],
        ];
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'NIK',
            'DOB (YYYY-MM-DD)',
            'Name',
            'Email',
            'Phone',
            'Password',
            'Location',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $locations = Location::pluck('name')->toArray();
                if (count($locations) > 0) {
                    $sheet = $event->sheet->getDelegate();
                    $rowIndex = 1;
                    foreach ($locations as $locationName) {
                        $sheet->setCellValue('Z'.$rowIndex, $locationName);
                        $rowIndex++;
                    }
                    $sheet->getColumnDimension('Z')->setVisible(false);

                    for ($i = 2; $i <= 1000; $i++) {
                        $validation = $sheet->getCell("H{$i}")->getDataValidation();
                        $validation->setType(DataValidation::TYPE_LIST);
                        $validation->setErrorStyle(DataValidation::STYLE_STOP);
                        $validation->setAllowBlank(true);
                        $validation->setShowInputMessage(true);
                        $validation->setShowErrorMessage(true);
                        $validation->setShowDropDown(true);
                        $validation->setFormula1('=$Z$1:$Z$'.($rowIndex - 1));
                    }
                }
            },
        ];
    }
}
