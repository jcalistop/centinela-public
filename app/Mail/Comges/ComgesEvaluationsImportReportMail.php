<?php

namespace App\Mail\Comges;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComgesEvaluationsImportReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public string $tipoEvaluacion,
        public int $processed,
        public int $successful,
        public array $errors,
    ) {}

    public function build()
    {
        return $this->subject('COMGES — Resultado importación '.$this->tipoEvaluacion)
            ->view('emails.comges.evaluations-import-report');
    }
}
