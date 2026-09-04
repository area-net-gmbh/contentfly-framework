<?php
namespace Custom\Traits;

trait User{


    /**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @PIM\Config(label="nameExample")
	*/
    protected $nameExample;
}
    
        