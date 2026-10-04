<?php

namespace App\View;

use App\Models\Labels\Label as LabelModel;
use App\Models\Labels\Sheet;
use Illuminate\Support\Collection;
use TCPDF;

class QrCheckoutLabel
{

    protected Collection $data;

    public function __construct()
    {
        $this->data = new Collection;
    }

    public function render(): string
    {
        $settings = $this->data->get('settings');
        $items = $this->data->get('items');
        $type = $this->data->get('type');
        $copies = max(1, (int) $this->data->get('copies', 1));

        abort_unless($settings && $settings->label2_enable, 422, 'QR Checkout Labels require the Enhanced Label Engine.');

        $template = LabelModel::find($settings->label2_template);
        abort_if($template === null, 422, 'The configured enhanced label template could not be found.');

        $template->validate();

        $pdf = new TCPDF(
            $template->getOrientation(),
            $template->getUnit(),
            [0 => $template->getWidth(), 1 => $template->getHeight(), 'Rotate' => $template->getRotation()]
        );

        $pdf->SetFontSubsetting(true);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, null, true);
        $pdf->SetCellMargins(0, 0, 0, 0);
        $pdf->SetCellPaddings(0, 0, 0, 0);
        $pdf->setCreator('Snipe-IT');
        $pdf->SetSubject('QR Checkout Labels');
        $template->preparePDF($pdf);

        $records = new Collection;

        foreach ($items as $item) {
            for ($copy = 0; $copy < $copies; $copy++) {
                $name = $item->name ?: trans('general.none');
                $identifier = $item->asset_tag ?? $item->model_number ?? null;

                $record = new Collection;
                $record->put('asset', $item);
                $record->put('id', $item->id);
                $record->put('tag', $identifier ?: (string) $item->id);
                $record->put('title', ucfirst($type));
                $record->put('barcode2d', (object) [
                    'type' => 'QRCODE,H',
                    'content' => route('qr-checkout.show', ['type' => $type, 'id' => $item->id]),
                ]);

                $fields = collect([
                    ['label' => '', 'value' => $name, 'dataSource' => 'name'],
                    $identifier ? ['label' => '', 'value' => (string) $identifier, 'dataSource' => 'identifier'] : null,
                    ['label' => 'ID', 'value' => (string) $item->id, 'dataSource' => 'id'],
                ])->filter()->values();

                $record->put('fields', $fields->take($template->getSupportFields()));
                $records->push($record);
            }
        }

        if ($template instanceof Sheet) {
            $template->setLabelIndexOffset(0);
        }

        $template->writeAll($pdf, $records);

        $filename = 'qr-checkout-'.str_slug($type).'-labels.pdf';
        return $pdf->Output($filename, 'S');
    }

    public function with($key, $value = null)
    {
        $this->data->put($key, $value);

        return $this;
    }

}
