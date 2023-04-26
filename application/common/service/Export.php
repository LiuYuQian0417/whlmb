<?php
declare(strict_types = 1);

namespace app\common\service;


use think\facade\Env;

class Export
{
    /**
     * 导出excel
     * @param $param
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function out($param)
    {
        ob_clean();
        require_once Env::get('extend_path') . 'PHPExcel/Classes/PHPExcel.php';
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getProperties()->setCreator("iShop");
        $objPHPExcel->getActiveSheet()->setTitle(date('YmdHis'));
        $objPHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setWrapText(true);
        $start = 65;
        foreach ($param['allowField'] as $field => $af) {
            $str = chr($start);
            // 设置标题
            $objPHPExcel
                ->setActiveSheetIndex(0)
                ->setCellValue("{$str}1", $af)
                ->getStyle("{$str}1", $af)
                ->applyFromArray([
                    'font' => ['bold' => true],
                ]);
            // 设置数据
            $length = 0;
            foreach ($param['data'] as $key => $val) {
                $length = max($length, strlen(strval($val->$field)));
                $key += 2;
                $objPHPExcel
                    ->setActiveSheetIndex(0)
                    ->setCellValueExplicit("{$str}{$key}", $val->$field, \PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $start++;
            $objPHPExcel->getActiveSheet()->getColumnDimension($str)->setWidth($length + 5);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $filename = date('YmdHis');
        header("Content-Disposition: attachment;filename={$filename}.xlsx");
        header('Cache-Control: max-age=0');
        header("Last-Modified:" . date('Y-m-d H:i:s') . " GMT");
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
    }

    public function in()
    {

    }
}