<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AntiBotFormRequest extends FormRequest
{
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
        return [
            //
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $formStart = (int) $this->input('form_start_time');
            $currentTime = (int) (microtime(true) * 1000);
            $timeDiff = $currentTime - $formStart;

            if ($timeDiff < 2000) {
                $validator->errors()->add('form_bot', 'Bot detected: Submitted too quickly.');
            }

            if ($this->input('js_enabled') !== 'true') {
                $validator->errors()->add('form_bot', 'Bot detected: JavaScript not enabled.');
            }

            if (!empty($this->input('website'))) {
                $validator->errors()->add('form_bot', 'Bot detected: Honeypot triggered.');
            }
        });
    }
}

