<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class JotformSync extends Model
{
    use HasUuids;

    protected $table = 'jotform_syncs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'form_id',
        'submission_id',
        'payload',
        'status_submit',
        'nama_lengkap',
        'email',
        'nama_sppg',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get a specific value from payload
     */
    public function getPayloadValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * Get answer from answers array by name
     */
    public function getAnswerByName(string $name): mixed
    {
        $answers = $this->getPayloadValue('answers', []);

        foreach ($answers as $answer) {
            if (isset($answer['name']) && $answer['name'] === $name) {
                return $answer['answer'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get answer as string (convert array to comma-separated values)
     */
    protected function getAnswerAsString(string $name): ?string
    {
        $value = $this->getAnswerByName($name);

        if ($value === null) {
            return null;
        }

        // If array, convert to comma-separated string
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    /**
     * Get nested answer value by name and key
     * For fields like address where answer is an object with multiple keys
     */
    protected function getNestedAnswerValue(string $name, string $key): ?string
    {
        $value = $this->getAnswerByName($name);

        if ($value === null) {
            return null;
        }

        // If array (object), extract the specific key
        if (is_array($value) && isset($value[$key])) {
            return (string) $value[$key];
        }

        return null;
    }

    /**
     * Helper to get alamat_sppg from answers (name = "alamatSppg", key = "addr_line1")
     */
    public function getAlamatSppgAttribute(): ?string
    {
        return $this->getNestedAnswerValue('alamatSppg', 'addr_line1');
    }

    /**
     * Delete all files associated with this submission
     */
    public function deleteSubmissionFiles(): void
    {
        try {
            // Delete the entire submission directory
            $submissionDir = "jotform/{$this->submission_id}";
            if (Storage::disk('public')->exists($submissionDir)) {
                Storage::disk('public')->deleteDirectory($submissionDir);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete submission files', [
                'submission_id' => $this->submission_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
