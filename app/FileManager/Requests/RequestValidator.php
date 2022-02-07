<?php

namespace App\FileManager\Requests;

use Storage;
use Illuminate\Foundation\Http\FormRequest;
use App\FileManager\Requests\CustomErrorMessage;
use App\FileManager\Services\ConfigService\DefaultConfigRepository;

class RequestValidator extends FormRequest
{
    use CustomErrorMessage;

    protected $config;

    public function __construct(DefaultConfigRepository $repo) 
    {
        $this->config = $repo;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $config = $this->config;
        return [
            'disk' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) use($config) {
                    if (!in_array($value, $this->config->getDiskList()) ||
                        !array_key_exists($value, config('filesystems.disks'))
                    ) {
                        return $fail('diskNotFound');
                    }
                },
            ],
            'path' => [
                'sometimes',
                'string',
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value && !Storage::disk($this->input('disk'))->exists($value)
                    ) {
                        return $fail('pathNotFound');
                    }
                },
            ],
        ];
    }

    /**
     * Not found message
     *
     * @return array|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public function message()
    {
        return 'notFound';
    }
}
