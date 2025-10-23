<?php

namespace Framework\Validation;

use Exception;
use Valitron\Validator;

class ValidationException extends Exception
{
    /**
     * The validator instance.
     *
     * @var \Valitron\Validator
     */
    protected $validator;

    /**
     * The status code to use for the response.
     *
     * @var int
     */
    protected $status = 422;

    /**
     * Create a new exception instance.
     *
     * @param  \Valitron\Validator  $validator
     */
    public function __construct(Validator $validator)
    {
        parent::__construct('The given data was invalid.');

        $this->validator = $validator;
    }

    /**
     * Create a new validation exception from a plain array of messages.
     *
     * @param  array  $messages
     * @return static
     */
    public static function withMessages(array $messages)
    {
        $validator = new Validator();

        foreach ($messages as $key => $value) {
            $validator->error($key, $value);
        }

        return new static($validator);
    }

    /**
     * Get all of the validation error messages.
     *
     * @return array
     */
    public function errors()
    {
        return $this->validator->errors();
    }

    /**
     * Set the HTTP status code to be used for the response.
     *
     * @param  int  $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the HTTP status code to be used for the response.
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }
}
