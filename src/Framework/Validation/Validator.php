<?php

namespace Framework\Validation;

use Valitron\Validator as ValitronValidator;
use Psr\Http\Message\ServerRequestInterface;

class Validator
{
    /**
     * Run the validator's rules against its data.
     * 
     * @param \Psr\Http\Message\ServerRequestInterface|array $data
     * @param array $rules
     * @return array
     */
    public static function validate($data, array $rules): array
    {
        if ($data instanceof ServerRequestInterface) {
            $data = $data->getParsedBody();
        }

        $validator = new ValitronValidator($data);
        $validator->mapFieldsRules($rules);

        if (!$validator->validate()) {
            throw new ValidationException($validator);
        }

        return (array) array_filter(
            $data,
            fn($key) => in_array(
                $key,
                array_keys($rules),
                true
            ),
            ARRAY_FILTER_USE_KEY
        );
    }
}
