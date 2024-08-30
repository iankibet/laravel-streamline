<?php

namespace Iankibet\Streamline\Attributes;
use Attribute;
#[Attribute(Attribute::TARGET_ALL)]
class Validate
{
    public $rules = [];
    public function __construct(array $rules)
    {
        $cleanedRules = [];
        foreach($rules as $key=>$value){
            if(is_int($key)) {
                $cleanedRules[$value] = '';
            }
            else{
                $cleanedRules[$key] = $value;
            }
        }
        $this->rules = $cleanedRules;
    }

    public function getRules(){
        return $this->rules;
    }
}
