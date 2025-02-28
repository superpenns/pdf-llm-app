<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;
use Smalot\PdfParser\Parser;
use OpenAI\Laravel\Facades\OpenAI;

class ProcessPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $originalName;
    public $LLM_error_message = 'Es tut uns leid, aber es gibt einige technische Probleme mit dem LLM-Anbieter. Bitte versuchen Sie, Ihr Dokument noch einmal hochzuladen.';

    public function __construct($filePath, $originalName)
    {
        $this->filePath = $filePath;
        $this->originalName = $originalName;
    }

    public function handle()
    {
        $pdfParser = new Parser();
        $pdf = $pdfParser->parseFile(storage_path("app/public/" . $this->filePath));
        $text = $pdf->getText();
        $text = $this->get_summary($text);
        Storage::disk('public')->put("results/{$this->originalName}.txt", $text);
    }

    public function get_summary($text)
    {
        #Checking if the extracted text is empty
        if (empty(trim($text))) {
            return "Es tut uns leid, aber wir können diese Art von Datei leider noch nicht bearbeiten.";
        }
        #Checking if text topic is allowed
        $is_allowed = $this->is_topic_allowed($text);
        if (is_null($is_allowed)) {
            return $this->LLM_error_message;
        } elseif (!$is_allowed) {
            return "Es tut uns leid, aber wir können Ihren Text nicht zusammenfassen. Themen, die Atomwaffen, andere zerstörerische Anwendungen der Kernenergie, SQL-Injektionen oder Pädophilie betreffen, sind in unserem Dienst nicht zugelassen.";
        }

        #Creating summary
        $result = $this->retryOpenAiRequest(function () use ($text) {
            return OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Bitte schreibe eine Zusammenfassung des Textes. Füge der Zusammenfassung keine Informationen hinzu, die nicht im Text enthalten sind. Wenn der Artikel Informationen über das Datum, die Zeit und den Ort eines Ereignisses enthält, ändere diese Informationen bitte nicht in einer Zusammenfassung.'],
                    ['role' => 'user', 'content' => 'Bitte füge der Zusammenfassung keine zusätzlichen Informationen hinzu, die nicht im Text enthalten sind. Hier ist der Text:' . $text],
                ],
            ]);
        });

        #Checking if the there is an error with the OpenAI API
        if (is_null($result)) {
            return $this->LLM_error_message;
        }
        $summary = $result->choices[0]->message->content;

        #Checking if the summary is in German and translating it to German if it is not
        $is_summary_german = $this->is_german($summary);

        if (is_null($is_summary_german)) {
            return $this->LLM_error_message;
        } elseif ($is_summary_german) {
            return $summary;
        } else {
            return $this->translate_to_german($summary) ?? $this->LLM_error_message;
        }
    }

    public function is_german($text)
    {
        $result = $this->retryOpenAiRequest(function () use ($text) {
            return OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',

                'messages' => [
                    ['role' => 'system', 'content' => 'Bitte prüfe, ob der angegebene Text auf Deutsch ist. Antworte mit nur einem Wort GERMAN, wenn der Text vollständig auf Deutsch ist. Antworte mit einem Wort NOT_GERMAN, wenn der Text nicht auf Deutsch ist oder eine Sprachmischung enthält.'],
                    ['role' => 'user', 'content' => 'Hier ist der Text:' . $text],
                ],
            ]);
        });

        if (is_null($result)) {
            return null;
        } else {
            $response = trim($result->choices[0]->message->content);

            return $response === "GERMAN";
        }
    }

    public function translate_to_german($text)
    {
        $result = $this->retryOpenAiRequest(function () use ($text) {
            return OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Du bist ein professioneller Übersetzer. Bitte übersetze den vorliegenden Text ins Deutsche. Antworte nur mit dem übersetzten Text und sonst nichts.'],
                    ['role' => 'user', 'content' => 'Hier ist der Text:' . $text],
                ],
            ]);
        });

        if (is_null($result)) {
            return null;
        } else {
            return $result->choices[0]->message->content;
        };
    }

    public function is_topic_allowed($text)
    {
        $result = $this->retryOpenAiRequest(function () use ($text) {
            return OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Bitte prüfe, ob es in dem angegebenen Text nicht um unerlaubte Themen geht. Beispiele von Atomwaffen, Atombomben oder eine andere zerstörerische Nutzung der Atomkraft, SQL Injektionen, Pädophilie. Wenn der Text von unerlaubte Themen handelt, antworte bitte mit nur einem Wort INVALID. Wenn der Text von einem anderen Thema handelt, antworten Sie bitte mit einem Wort VALID.'],
                    ['role' => 'user', 'content' => 'Hier ist der Text:' . $text],
                ],
            ]);
        });

        if (is_null($result)) {
            return null;
        } else {
            $response = trim($result->choices[0]->message->content);

            return $response === "VALID";
        }
    }

    private function retryOpenAiRequest(callable $callback, $maxAttempts = 3, $baseDelay = 2)
    {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return $callback();
            } catch (Exception $e) {
                Log::error("OpenAI API call failed: " . $e->getMessage());

                if ($attempt === $maxAttempts - 1) {
                    return null;
                }

                sleep(pow($baseDelay, $attempt));
                $attempt++;
            }
        }

        return null;
    }
}
