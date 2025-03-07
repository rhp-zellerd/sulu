<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Content\Compat;

/**
 * Type for structures, like ghost or shadow.
 */
class StructureType
{
    /**
     * creates a new ghost type.
     *
     * @param string $localization
     *
     * @return StructureType
     */
    public static function getGhost($localization)
    {
        return new self('ghost', $localization);
    }

    /**
     * creates a new ghost type.
     *
     * @param string $localization
     *
     * @return StructureType
     */
    public static function getShadow($localization)
    {
        return new self('shadow', $localization);
    }

    /**
     * @param string $name
     * @param string $value
     */
    private function __construct(private $name, private $value)
    {
    }

    /**
     * return name of type.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * return value of type.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * returns a array representation.
     *
     * @return array
     */
    public function toArray()
    {
        return ['name' => $this->getName(), 'value' => $this->getValue()];
    }
}
