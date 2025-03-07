<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\SmartContent;

/**
 * Interface of DatasourceItem.
 */
interface DatasourceItemInterface
{
    /**
     * Returns id of item.
     *
     * @return string|int
     */
    public function getId();

    /**
     * Returns title of the item.
     *
     * @return string
     */
    public function getTitle();

    /**
     * Returns full qualified title of item.
     * For example path or breadcrumb.
     *
     * @return string
     */
    public function getPath();

    /**
     * Returns URL to image.
     *
     * @return string|null
     */
    public function getImage();
}
