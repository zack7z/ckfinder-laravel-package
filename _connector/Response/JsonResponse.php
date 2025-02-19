<?php

/*
 * CKFinder
 * ========
 * https://ckeditor.com/ckfinder/
 * Copyright (c) 2007-2021, CKSource - Frederico Knabben. All rights reserved.
 *
 * The software, this file and its contents are subject to the CKFinder
 * License. Please read the license.txt file before using, installing, copying,
 * modifying or distribute this file or part of its contents. The contents of
 * this file is part of the Source Code of CKFinder.
 */

namespace CKSource\CKFinder\Response;

use Symfony\Component\HttpFoundation;

/**
 * The CKFinder JSON response class.
 */
class JsonResponse extends HttpFoundation\JsonResponse
{
    protected $rawData;

    public function __construct($data = null, $status = 200, $headers = [])
    {
        if (null === $data) {
            $data = new \stdClass();
        }

        parent::__construct($data, $status, $headers);

        $this->rawData = $data;
    }

    public function getData()
    {
        return $this->rawData;
    }

    public function setData($data = []): static
    {
        $this->rawData = $data;

        return parent::setData($this->rawData);
    }

    public function withError($errorNumber, $errorMessage = null)
    {
        $errorData = ['number' => $errorNumber];

        if ($errorMessage) {
            $errorData['message'] = $errorMessage;
        }

        $data = (array) $this->rawData;

        $data = ['error' => $errorData] + $data;

        $this->setData($data);

        return $this;
    }
}
