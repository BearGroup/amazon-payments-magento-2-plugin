<?php

/**
 * Copyright 2020 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *  http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace Amazon\Pay\Domain;

class AmazonAddressDecoratorJp implements AmazonAddressInterface
{
    private const CITY_FROM_LINE_REGEX = '/^.*?[市区町村]/u';

    /**
     * @var AmazonAddressInterface
     */
    private $amazonAddress;

    /**
     * AmazonAddressDecoratorJp constructor
     *
     * @param AmazonAddressInterface $amazonAddress
     */
    public function __construct(
        AmazonAddressInterface $amazonAddress
    ) {
        $this->amazonAddress = $amazonAddress;
    }

    /**
     * @inheritDoc
     */
    public function getLines()
    {
        $lines = $this->amazonAddress->getLines();
        $city = $this->resolveCity();

        if (!empty($city) && isset($lines[1]) && strpos($lines[1], $city) === 0) {
            $lines[1] = ltrim(str_replace($city, '', $lines[1]));
            $lines = array_filter($lines);
        }

        return $lines;
    }

    /**
     * @inheritDoc
     */
    public function getCompany()
    {
        return $this->amazonAddress->getCompany();
    }

    /**
     * @inheritDoc
     */
    public function getFirstName() {
        $name = $this->amazonAddress->getFirstName();
        $parts = explode(' ', trim($name), 2);
        return $parts[0] ?: '-';
    }

    /**
     * @inheritDoc
     */
    public function getLastName() {
        $name = $this->amazonAddress->getFirstName();
        $parts = explode(' ', trim($name), 2);
        return $parts[1] ?? ($this->amazonAddress->getLastName() ?: '-');
    }

    /**
     * @inheritDoc
     */
    public function getCity()
    {
        $city = $this->resolveCity('-');

        return $city !== '' ? $city : '-';
    }

    /**
     * @inheritDoc
     */
    public function getState()
    {
        $state = $this->amazonAddress->getState();

        if (empty($state)) {
            $lines = $this->amazonAddress->getLines();
            $targetLine = $lines[1] ?? '';

            if (preg_match('/^.*?[都道府県]/u', $targetLine, $matches)) {
                return $matches[0];
            }
        }

        return $state;
    }

    /**
     * @inheritDoc
     */
    public function getPostCode()
    {
        return $this->amazonAddress->getPostCode();
    }

    /**
     * @inheritDoc
     */
    public function getCountryCode()
    {
        return $this->amazonAddress->getCountryCode();
    }

    /**
     * @inheritDoc
     */
    public function getTelephone()
    {
        return $this->amazonAddress->getTelephone();
    }

    /**
     * @inheritDoc
     */
    public function getLine($lineNumber)
    {
        $lines = $this->getLines();
        return $lines[$lineNumber] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function shiftLines($times)
    {
        return $this->amazonAddress->shiftLines($times);
    }

    /**
     * @inheritDoc
     */
    public function setCompany($company)
    {
        return $this->amazonAddress->setCompany($company);
    }

    /**
     * @param string|null $fallback
     * @return string
     */
    private function resolveCity(?string $fallback = null): string
    {
        $city = (string) $this->amazonAddress->getCity();
        if ($city !== '') {
            return $city;
        }

        $lines = (array) $this->amazonAddress->getLines();
        $targetLine = (string) ($lines[1] ?? '');

        if ($targetLine !== '' && preg_match(self::CITY_FROM_LINE_REGEX, $targetLine, $matches)) {
            return $matches[0];
        }

        return $targetLine !== '' ? $targetLine : ($fallback ?? '');
    }
}
