<?php

namespace Pulsarcode\Framework\Test;

/**
 * Class TestCommentsInBlock
 *
 * @package Pulsarcode\Framework\Test
 */
class TestCommentsInBlock
{
    /**
     * @type string TYPE_CAST_NAME_DESC My description
     */
    const TYPE_CAST_NAME_DESC = 'Constant with @type + cast + name + description';

    /**
     * @type string CONST_WITH_TYPE_AND_NAME
     */
    const TYPE_CAST_NAME = 'Constant with @type + cast + name';

    /**
     * @type string
     */
    const TYPE_CAST = 'Constant with @type + cast';

    /**
     * @var string VAR_CAST_NAME_DESC My description
     */
    const VAR_CAST_NAME_DESC = 'Constant with @var + cast + name + description';

    /**
     * @var string VAR_CAST_NAME
     */
    const VAR_CAST_NAME = 'Constant with @var + cast + name';

    /**
     * @var string
     */
    const VAR_CAST = 'Constant with @var + cast';

    /**
     * My description
     */
    const CONST_DESC = 'Constant with only description';

    /**
     * @type string $publicTypeCastNameDesc My description
     */
    public $publicTypeCastNameDesc = 'Property public with @type + cast + name + description';

    /**
     * @type string $publicTypeCastName
     */
    public $publicTypeCastName = 'Property public with @type + cast + name';

    /**
     * @type string
     */
    public $publicTypeCast = 'Property public with @type + cast';

    /**
     * My description
     */
    public $publicTypeOnlyDesc = 'Property public with only description';

    /**
     * @type string $publicStaticTypeCastNameDesc My description
     */
    public static $publicStaticTypeCastNameDesc = 'Property public static with @type + cast + name + description';

    /**
     * @type string $publicStaticTypeCastName
     */
    public static $publicStaticTypeCastName = 'Property public static with @type + cast + name';

    /**
     * @type string
     */
    public static $publicStaticTypeCast = 'Property public static with @type + cast';

    /**
     * My description
     */
    public static $publicStaticTypeOnlyDesc = 'Property public static with only description';

    /**
     * @type string $protectedTypeCastNameDesc My description
     */
    protected $protectedTypeCastNameDesc = 'Property protected with @type + cast + name + description';

    /**
     * @type string $protectedTypeCastName
     */
    protected $protectedTypeCastName = 'Property protected with @type + cast + name';

    /**
     * @type string
     */
    protected $protectedTypeCast = 'Property protected with @type + cast';

    /**
     * My description
     */
    protected $protectedTypeOnlyDesc = 'Property protected with only description';

    /**
     * @type string $protectedStaticTypeCastNameDesc My description
     */
    protected static $protectedStaticTypeCastNameDesc = 'Property protected static with @type + cast + name + description';

    /**
     * @type string $protectedStaticTypeCastName
     */
    protected static $protectedStaticTypeCastName = 'Property protected static with @type + cast + name';

    /**
     * @type string
     */
    protected static $protectedStaticTypeCast = 'Property protected static with @type + cast';

    /**
     * My description
     */
    protected static $protectedStaticTypeOnlyDesc = 'Property protected static with only description';

    /**
     * @type string $privateTypeCastNameDesc My description
     */
    private $privateTypeCastNameDesc = 'Property private with @type + cast + name + description';

    /**
     * @type string $privateTypeCastName
     */
    private $privateTypeCastName = 'Property private with @type + cast + name';

    /**
     * @type string
     */
    private $privateTypeCast = 'Property private with @type + cast';

    /**
     * My description
     */
    private $privateTypeOnlyDesc = 'Property private with only description';

    /**
     * @type string $privateStaticTypeCastNameDesc My description
     */
    private static $privateStaticTypeCastNameDesc = 'Property private static with @type + cast + name + description';

    /**
     * @type string $privateStaticTypeCastName
     */
    private static $privateStaticTypeCastName = 'Property private static with @type + cast + name';

    /**
     * @type string
     */
    private static $privateStaticTypeCast = 'Property private static with @type + cast';

    /**
     * My description
     */
    private static $privateStaticTypeOnlyDesc = 'Property private static with only description';

    /**
     * @var string $publicVarCastNameDesc My description
     */
    public $publicVarCastNameDesc = 'Property public with @var + cast + name + description';

    /**
     * @var string $publicVarCastName
     */
    public $publicVarCastName = 'Property public with @var + cast + name';

    /**
     * @var string
     */
    public $publicVarCast = 'Property public with @var + cast';

    /**
     * My description
     */
    public $publicVarOnlyDesc = 'Property public with only description';

    /**
     * @var string $publicStaticVarCastNameDesc My description
     */
    public static $publicStaticVarCastNameDesc = 'Property public static with @var + cast + name + description';

    /**
     * @var string $publicStaticVarCastName
     */
    public static $publicStaticVarCastName = 'Property public static with @var + cast + name';

    /**
     * @var string
     */
    public static $publicStaticVarCast = 'Property public static with @var + cast';

    /**
     * My description
     */
    public static $publicStaticVarOnlyDesc = 'Property public static with only description';

    /**
     * @var string $protectedVarCastNameDesc My description
     */
    protected $protectedVarCastNameDesc = 'Property protected with @var + cast + name + description';

    /**
     * @var string $protectedVarCastName
     */
    protected $protectedVarCastName = 'Property protected with @var + cast + name';

    /**
     * @var string
     */
    protected $protectedVarCast = 'Property protected with @var + cast';

    /**
     * My description
     */
    protected $protectedVarOnlyDesc = 'Property protected with only description';

    /**
     * @var string $protectedStaticVarCastNameDesc My description
     */
    protected static $protectedStaticVarCastNameDesc = 'Property protected static with @var + cast + name + description';

    /**
     * @var string $protectedStaticVarCastName
     */
    protected static $protectedStaticVarCastName = 'Property protected static with @var + cast + name';

    /**
     * @var string
     */
    protected static $protectedStaticVarCast = 'Property protected static with @var + cast';

    /**
     * My description
     */
    protected static $protectedStaticVarOnlyDesc = 'Property protected static with only description';

    /**
     * @var string $privateVarCastNameDesc My description
     */
    private $privateVarCastNameDesc = 'Property private with @var + cast + name + description';

    /**
     * @var string $privateVarCastName
     */
    private $privateVarCastName = 'Property private with @var + cast + name';

    /**
     * @var string
     */
    private $privateVarCast = 'Property private with @var + cast';

    /**
     * My description
     */
    private $privateVarOnlyDesc = 'Property private with only description';

    /**
     * @var string $privateStaticVarCastNameDesc My description
     */
    private static $privateStaticVarCastNameDesc = 'Property private static with @var + cast + name + description';

    /**
     * @var string $privateStaticVarCastName
     */
    private static $privateStaticVarCastName = 'Property private static with @var + cast + name';

    /**
     * @var string
     */
    private static $privateStaticVarCast = 'Property private static with @var + cast';

    /**
     * My description
     */
    private static $privateStaticVarOnlyDesc = 'Property private static with only description';
}

/**
 * Class TestCommentsInLine
 *
 * @package Pulsarcode\Framework\Test
 */
class TestCommentsInLine
{
    /** @type string TYPE_CAST_NAME_DESC My description */
    const TYPE_CAST_NAME_DESC = 'Constant with @type + cast + name + description';

    /** @type string CONST_WITH_TYPE_AND_NAME */
    const TYPE_CAST_NAME = 'Constant with @type + cast + name';

    /** @type string */
    const TYPE_CAST = 'Constant with @type + cast';

    /** @var string VAR_CAST_NAME_DESC My description */
    const VAR_CAST_NAME_DESC = 'Constant with @var + cast + name + description';

    /** @var string VAR_CAST_NAME */
    const VAR_CAST_NAME = 'Constant with @var + cast + name';

    /** @var string */
    const VAR_CAST = 'Constant with @var + cast';

    /** My description */
    const CONST_DESC = 'Constant with only description';

    /** @type string $publicTypeCastNameDesc My description */
    public $publicTypeCastNameDesc = 'Property public with @type + cast + name + description';

    /** @type string $publicTypeCastName */
    public $publicTypeCastName = 'Property public with @type + cast + name';

    /** @type string */
    public $publicTypeCast = 'Property public with @type + cast';

    /** My description */
    public $publicTypeOnlyDesc = 'Property public with only description';

    /** @type string $publicStaticTypeCastNameDesc My description */
    public static $publicStaticTypeCastNameDesc = 'Property public static with @type + cast + name + description';

    /** @type string $publicStaticTypeCastName */
    public static $publicStaticTypeCastName = 'Property public static with @type + cast + name';

    /** @type string */
    public static $publicStaticTypeCast = 'Property public static with @type + cast';

    /** My description */
    public static $publicStaticTypeOnlyDesc = 'Property public static with only description';

    /** @type string $protectedTypeCastNameDesc My description */
    protected $protectedTypeCastNameDesc = 'Property protected with @type + cast + name + description';

    /** @type string $protectedTypeCastName */
    protected $protectedTypeCastName = 'Property protected with @type + cast + name';

    /** @type string */
    protected $protectedTypeCast = 'Property protected with @type + cast';

    /** My description */
    protected $protectedTypeOnlyDesc = 'Property protected with only description';

    /** @type string $protectedStaticTypeCastNameDesc My description */
    protected static $protectedStaticTypeCastNameDesc = 'Property protected static with @type + cast + name + description';

    /** @type string $protectedStaticTypeCastName */
    protected static $protectedStaticTypeCastName = 'Property protected static with @type + cast + name';

    /** @type string */
    protected static $protectedStaticTypeCast = 'Property protected static with @type + cast';

    /** My description */
    protected static $protectedStaticTypeOnlyDesc = 'Property protected static with only description';

    /** @type string $privateTypeCastNameDesc My description */
    private $privateTypeCastNameDesc = 'Property private with @type + cast + name + description';

    /** @type string $privateTypeCastName */
    private $privateTypeCastName = 'Property private with @type + cast + name';

    /** @type string */
    private $privateTypeCast = 'Property private with @type + cast';

    /** My description */
    private $privateTypeOnlyDesc = 'Property private with only description';

    /** @type string $privateStaticTypeCastNameDesc My description */
    private static $privateStaticTypeCastNameDesc = 'Property private static with @type + cast + name + description';

    /** @type string $privateStaticTypeCastName */
    private static $privateStaticTypeCastName = 'Property private static with @type + cast + name';

    /** @type string */
    private static $privateStaticTypeCast = 'Property private static with @type + cast';

    /** My description */
    private static $privateStaticTypeOnlyDesc = 'Property private static with only description';

    /** @var string $publicVarCastNameDesc My description */
    public $publicVarCastNameDesc = 'Property public with @var + cast + name + description';

    /** @var string $publicVarCastName */
    public $publicVarCastName = 'Property public with @var + cast + name';

    /** @var string */
    public $publicVarCast = 'Property public with @var + cast';

    /** My description */
    public $publicVarOnlyDesc = 'Property public with only description';

    /** @var string $publicStaticVarCastNameDesc My description */
    public static $publicStaticVarCastNameDesc = 'Property public static with @var + cast + name + description';

    /** @var string $publicStaticVarCastName */
    public static $publicStaticVarCastName = 'Property public static with @var + cast + name';

    /** @var string */
    public static $publicStaticVarCast = 'Property public static with @var + cast';

    /** My description */
    public static $publicStaticVarOnlyDesc = 'Property public static with only description';

    /** @var string $protectedVarCastNameDesc My description */
    protected $protectedVarCastNameDesc = 'Property protected with @var + cast + name + description';

    /** @var string $protectedVarCastName */
    protected $protectedVarCastName = 'Property protected with @var + cast + name';

    /** @var string */
    protected $protectedVarCast = 'Property protected with @var + cast';

    /** My description */
    protected $protectedVarOnlyDesc = 'Property protected with only description';

    /** @var string $protectedStaticVarCastNameDesc My description */
    protected static $protectedStaticVarCastNameDesc = 'Property protected static with @var + cast + name + description';

    /** @var string $protectedStaticVarCastName */
    protected static $protectedStaticVarCastName = 'Property protected static with @var + cast + name';

    /** @var string */
    protected static $protectedStaticVarCast = 'Property protected static with @var + cast';

    /** My description */
    protected static $protectedStaticVarOnlyDesc = 'Property protected static with only description';

    /** @var string $privateVarCastNameDesc My description */
    private $privateVarCastNameDesc = 'Property private with @var + cast + name + description';

    /** @var string $privateVarCastName */
    private $privateVarCastName = 'Property private with @var + cast + name';

    /** @var string */
    private $privateVarCast = 'Property private with @var + cast';

    /** My description */
    private $privateVarOnlyDesc = 'Property private with only description';

    /** @var string $privateStaticVarCastNameDesc My description */
    private static $privateStaticVarCastNameDesc = 'Property private static with @var + cast + name + description';

    /** @var string $privateStaticVarCastName */
    private static $privateStaticVarCastName = 'Property private static with @var + cast + name';

    /** @var string */
    private static $privateStaticVarCast = 'Property private static with @var + cast';

    /** My description */
    private static $privateStaticVarOnlyDesc = 'Property private static with only description';
}

/**
 * Class TestCommentsInTwoLines
 *
 * @package Pulsarcode\Framework\Test
 */
class TestCommentsInTwoLines
{
    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string TYPE_CAST_NAME_DESC My description
     */
    const TYPE_CAST_NAME_DESC = 'Constant with @type + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string CONST_WITH_TYPE_AND_NAME
     */
    const TYPE_CAST_NAME = 'Constant with @type + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string
     */
    const TYPE_CAST = 'Constant with @type + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string VAR_CAST_NAME_DESC My description
     */
    const VAR_CAST_NAME_DESC = 'Constant with @var + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string VAR_CAST_NAME
     */
    const VAR_CAST_NAME = 'Constant with @var + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string
     */
    const VAR_CAST = 'Constant with @var + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    const CONST_DESC = 'Constant with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $publicTypeCastNameDesc My description
     */
    public $publicTypeCastNameDesc = 'Property public with @type + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $publicTypeCastName
     */
    public $publicTypeCastName = 'Property public with @type + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string
     */
    public $publicTypeCast = 'Property public with @type + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    public $publicTypeOnlyDesc = 'Property public with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $publicStaticTypeCastNameDesc My description
     */
    public static $publicStaticTypeCastNameDesc = 'Property public static with @type + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $publicStaticTypeCastName
     */
    public static $publicStaticTypeCastName = 'Property public static with @type + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string
     */
    public static $publicStaticTypeCast = 'Property public static with @type + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    public static $publicStaticTypeOnlyDesc = 'Property public static with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $protectedTypeCastNameDesc My description
     */
    protected $protectedTypeCastNameDesc = 'Property protected with @type + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $protectedTypeCastName
     */
    protected $protectedTypeCastName = 'Property protected with @type + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string
     */
    protected $protectedTypeCast = 'Property protected with @type + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    protected $protectedTypeOnlyDesc = 'Property protected with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $protectedStaticTypeCastNameDesc My description
     */
    protected static $protectedStaticTypeCastNameDesc = 'Property protected static with @type + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $protectedStaticTypeCastName
     */
    protected static $protectedStaticTypeCastName = 'Property protected static with @type + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string
     */
    protected static $protectedStaticTypeCast = 'Property protected static with @type + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    protected static $protectedStaticTypeOnlyDesc = 'Property protected static with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $privateTypeCastNameDesc My description
     */
    private $privateTypeCastNameDesc = 'Property private with @type + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $privateTypeCastName
     */
    private $privateTypeCastName = 'Property private with @type + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string
     */
    private $privateTypeCast = 'Property private with @type + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    private $privateTypeOnlyDesc = 'Property private with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $privateStaticTypeCastNameDesc My description
     */
    private static $privateStaticTypeCastNameDesc = 'Property private static with @type + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string $privateStaticTypeCastName
     */
    private static $privateStaticTypeCastName = 'Property private static with @type + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @type string
     */
    private static $privateStaticTypeCast = 'Property private static with @type + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    private static $privateStaticTypeOnlyDesc = 'Property private static with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $publicVarCastNameDesc My description
     */
    public $publicVarCastNameDesc = 'Property public with @var + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $publicVarCastName
     */
    public $publicVarCastName = 'Property public with @var + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string
     */
    public $publicVarCast = 'Property public with @var + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    public $publicVarOnlyDesc = 'Property public with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $publicStaticVarCastNameDesc My description
     */
    public static $publicStaticVarCastNameDesc = 'Property public static with @var + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $publicStaticVarCastName
     */
    public static $publicStaticVarCastName = 'Property public static with @var + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string
     */
    public static $publicStaticVarCast = 'Property public static with @var + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    public static $publicStaticVarOnlyDesc = 'Property public static with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $protectedVarCastNameDesc My description
     */
    protected $protectedVarCastNameDesc = 'Property protected with @var + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $protectedVarCastName
     */
    protected $protectedVarCastName = 'Property protected with @var + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string
     */
    protected $protectedVarCast = 'Property protected with @var + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    protected $protectedVarOnlyDesc = 'Property protected with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $protectedStaticVarCastNameDesc My description
     */
    protected static $protectedStaticVarCastNameDesc = 'Property protected static with @var + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $protectedStaticVarCastName
     */
    protected static $protectedStaticVarCastName = 'Property protected static with @var + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string
     */
    protected static $protectedStaticVarCast = 'Property protected static with @var + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    protected static $protectedStaticVarOnlyDesc = 'Property protected static with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $privateVarCastNameDesc My description
     */
    private $privateVarCastNameDesc = 'Property private with @var + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $privateVarCastName
     */
    private $privateVarCastName = 'Property private with @var + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string
     */
    private $privateVarCast = 'Property private with @var + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    private $privateVarOnlyDesc = 'Property private with only description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $privateStaticVarCastNameDesc My description
     */
    private static $privateStaticVarCastNameDesc = 'Property private static with @var + cast + name + description';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string $privateStaticVarCastName
     */
    private static $privateStaticVarCastName = 'Property private static with @var + cast + name';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * @var string
     */
    private static $privateStaticVarCast = 'Property private static with @var + cast';

    /**
     * My short description here. My long description with more info and more examples here
     *
     * My description
     */
    private static $privateStaticVarOnlyDesc = 'Property private static with only description';
}
