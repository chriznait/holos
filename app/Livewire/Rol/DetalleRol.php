<?php

namespace App\Livewire\Rol;

use App\Models\EstadoRol;
use App\Models\Rol;
use App\Models\RolDetalle;
use App\Models\Turno;
use Illuminate\Http\Request;
use Livewire\Attributes\On;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DetalleRol extends Component
{
    public $tituloModal, $idModal;
    public $rol, $diasMes;
    public $tituloModalEstado, $idModalEstado, $tituloModalTurno, $idModalTurno, $turnos, $rolDetalle;
    public $crud;

    function mount(Request $request) : void {
        $this->tituloModalEstado    = "Actualiza estado";
        $this->idModalEstado        = "mdl-actualiza-estado";
        $this->tituloModalTurno     = "Actualiza turno";
        $this->idModalTurno         = "mdl-turno";
        $this->turnos               = Turno::all();
        $this->crud                 = $request->attributes->get('permisos');

        $this->tituloModal          = "Detalle Rol";
        $this->idModal              = "mdl-rol-detalle";
        $this->rol                  = new Rol();
        $this->diasMes              = [];

        $this->reseteaRolDetalle();
    }
    #[On('inicializaDatos')]
    function inicializaDatos($id) : void {
        $this->rol = Rol::with('empleados.detalles.rTurno')->find($id);
        $this->diasMes = getDiasMes($this->rol->anio, $this->rol->mes);
    }
    #[On('muestraDetalle')]
    function mostrarDetalle($id) : void {
        $this->inicializaDatos($id);
        $this->dispatch('openModal', $this->idModal);
    }
    function cierraDetalle(){
        $this->dispatch('closeModal', $this->idModal);
    }
    function setEstado($estadoId) : void {
        $this->rol->estadoId = $estadoId;
        $this->rol->save();
        $this->cierraDetalle();

        $resp["type"] = 'success';
        $resp["message"] = 'Actualizado con exito';

        $this->dispatch('alert', $resp);

        $this->dispatch('refresh')->to(Administracion::class);
    }
    function descargarFormato(){
        $rol = $this->rol;

        $dias = getDiasMes($rol->anio, $rol->mes);
        $turnos = Turno::all();

        $bodyStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR, // Establecer el estilo del borde
                    'color' => ['rgb' => '000000'], // Establecer el color del borde (en este caso, negro)
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ];
        $headerStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR, // Establecer el estilo del borde
                    'color' => ['rgb' => '000000'], // Establecer el color del borde (en este caso, negro)
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, // Tipo de relleno sólido
                'startColor' => ['argb' => 'D8E4BC'], // Color de fondo (en este caso, amarillo)
            ],
        ];


        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->createSheet();
        $spreadsheet->setActiveSheetIndex(1);

        
        foreach ($turnos as $i => $turno) {
            $sheet1->setCellValue('A'.($i+1), $turno->abrev)
            ->setCellValue('B'.($i+1), $turno->horas);
        }
        /* $sheet1->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN); */
        
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rol');

        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(25);
        for ($i = 3; $i <= 45 ; $i++) {
            $sheet->getColumnDimension(numeroLetra($i))->setWidth(5);
        }
        $sheet->getStyle('A')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('A4:'.numeroLetra($turnos->count()+count($dias)+3).'5')->applyFromArray($headerStyle);
        $sheet->getStyle('A6:'.numeroLetra($turnos->count()+count($dias)+3).'20')->applyFromArray($bodyStyle);


        $sheet->setCellValue('A2', 'Formato de rol correspondiente a '.$rol->mes().' del '.$rol->anio);

        
        $ini = 4;
        for ($i=1; $i <= count($dias); $i++) { 
            $letra = numeroLetra($i+2);
            $sheet->setCellValue($letra.$ini, $dias[$i]['inicial']);
            $sheet->setCellValue($letra.($ini+1), $i);
        }
        $ini++;

        $sheet->setCellValue('A'.$ini,'DNI')
                ->setCellValue('B'.$ini,'NOMBRES');
        foreach ($turnos as $i => $turno) {
            $letra = numeroLetra($i+count($dias)+3);
            $sheet->setCellValue($letra.$ini, $turno->abrev);
        }
        $sheet->setCellValue(numeroLetra($turnos->count()+count($dias)+3).$ini, 'Total Horas');

        $ini++;

        for ($i=0; $i < 15; $i++) {
            $formulaTotal = [];
            foreach ($turnos as $j => $turno) {
                $letra = numeroLetra($j+count($dias)+3);
                $formula = '=COUNTIF('.numeroLetra(3).$ini.':'.numeroLetra(count($dias)+2).$ini.', "'.$turno->abrev.'")';
                $sheet->setCellValue($letra.$ini, $formula);
                $formulaTotal[] = $letra.$ini.'*'.$turno->horas;
            }
            $sheet->setCellValue(numeroLetra($turnos->count()+count($dias)+3).$ini, '=SUM('.implode(',',$formulaTotal).')');
            $ini++;
        }
        $ini+=1;
        foreach ($turnos as $i => $turno) {
            $sheet->setCellValue('E'.$ini+$i, $turno->abrev)
                    ->setCellValue('F'.$ini+$i, $turno->descripcion);
        }

        $writer = new Xlsx($spreadsheet);

        // Guardar el archivo en memoria
        $stream = fopen('php://temp', 'r+');
        $writer->save($stream);

        // Leer el contenido del flujo de salida
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        // Devolver el archivo como respuesta HTTP
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'rol '.$rol->mes().'.xlsx');
    }
    function modalSubirRol() : void {
        $this->dispatch('openModal', 'mdl-upload-rol');
    }

    function abrirModalEstado($idEstado) : void {
        $estado = EstadoRol::find($idEstado);
        $this->rol->estadoId = $idEstado;
        $this->tituloModalEstado = "¿Seguro que desea cambiar estado del rol a ".$estado->descripcion."?";
        $this->dispatch('openModal', $this->idModalEstado);
    }
    function rules() : array {
        return [
            'rol.validacion' => '',
            'rol.estadoId' => '',
            'rolDetalle.rolEmpleadoId' => '',
            'rolDetalle.turno' => '',
            'rolDetalle.dia' => '',
        ];
    }
    function guardarEstado() : void {

        $this->rol->revisaId = session()->get('empleadoId');
        $this->rol->fechaHoraRevisa = now();
        $this->rol->save();

        $this->cierraDetalle();
        $this->dispatch('closeModal', $this->idModalEstado);

        $resp["type"] = 'success';
        $resp["message"] = 'Actualizado con exito';

        $this->dispatch('alert', $resp);

        $this->dispatch('refresh')->to(Administracion::class);
        
    }
    function publicar() : void {

        $this->rol->estadoId = 1;
        $this->rol->publicaId = session()->get('empleadoId');
        $this->rol->fechaHoraPublica = now();
        $this->rol->save();

        $this->cierraDetalle();
        $this->dispatch('closeModal', $this->idModalEstado);

        $resp["type"] = 'success';
        $resp["message"] = 'Publicado con exito';

        $this->dispatch('alert', $resp);

        $this->dispatch('refresh')->to(Administracion::class);
    }

    /*
        TURNOS
    */
    function reseteaRolDetalle() : void {
        $this->rolDetalle = new RolDetalle();
    }

    function muestraModalTurno($rolEmpleadoId, $turno, $dia) : void {
        /* $this->reseteaRolDetalle($data); */
        $rolDetalle = RolDetalle::where(['rolEmpleadoId' => $rolEmpleadoId, 'turno' => $turno, 'dia' => $dia])
                                    ->first();
        if(!is_null($rolDetalle))
            $this->rolDetalle = $rolDetalle;
        else{
            $this->rolDetalle = new RolDetalle();
            $this->rolDetalle->rolEmpleadoId    = $rolEmpleadoId;
            $this->rolDetalle->turno            = $turno;
            $this->rolDetalle->dia              = $dia;
        }

        $this->dispatch('openModal', $this->idModalTurno);
    }
    function guardarTurno() : void {

        if ($this->rol->estadoId == 3 || is_null($this->rol->estadoId)){
            if(empty($this->rolDetalle->turno) && isset($this->rolDetalle->id) && !is_null($this->rolDetalle->id)){
                $this->rolDetalle->delete();
            }else{
                $this->rolDetalle->save();
            }
    
            $resp['type'] = 'success';
            $resp['message'] = 'Actualizado con exito';
            $this->dispatch('closeModal', $this->idModalTurno);
            $this->inicializaDatos($this->rol->id);
            $this->resetValidation();
        }else{
            $resp['type'] = 'error';
            $resp['message'] = 'No se puede actualizar un rol aprobado o pendiente';
        }
        $this->dispatch('alert', $resp);
    }
    public function render()
    {
        return view('livewire.rol.detalle-rol');
    }
}
