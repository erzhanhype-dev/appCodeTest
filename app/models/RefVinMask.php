<?php

class RefVinMask extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public ?int $id = null;

    /**
     *
     * @var string
     */
    public ?string $name = null;

    /**
     *
     * @var integer|null
     */
    public ?int $created_at = null;

    /**
     *
     * @var integer|null
     */
    public ?int $created_by = null;

    /**
     *
     * @var integer|null
     */
    public ?int $deleted_at = null;

    /**
     *
     * @var integer|null
     */
    public ?int $deleted_by = null;

    /**
     *
     * @var string
     */
    public string $status;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("ref_vin_mask");
    }

    public static function checkVinMask(string $vin, string $mask)
    {
        $match = false;
        $chars = str_split($mask);
        $vin_chars = str_split($vin);

        $special_chars_index_num = [];
        $special_chars_count = 0;

        foreach ($chars as $char) {
            $special_chars_index_num[] = $char;

            if($char == '*' || $char == '?') continue;
            $special_chars_count++;
        }

        $matched_char_count = 0;

        foreach($vin_chars as $key => $vin){
            if(isset($special_chars_index_num[$key])){
                if($special_chars_index_num[$key] == '*' || $special_chars_index_num[$key] == '?') continue;
                $special_char_vin = $special_chars_index_num[$key];
                if($special_char_vin == $vin) $matched_char_count++;
            }
        }

        if($matched_char_count == $special_chars_count) $match = true;

        return $match;
    }

}
