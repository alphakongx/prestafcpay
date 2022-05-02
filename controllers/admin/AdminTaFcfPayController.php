<?php
/**
 *  Copyright (C) TA - All Rights Reserved
 *
 *  Unauthorized copying and editing of this file is strictly prohibited
 *  Proprietary and confidential
 *
 *  @author    TA
 *  @copyright 2020-2022 TA
 *  @license   Commercial
 */

class AdminTaFcfPayController extends ModuleAdminController
{
    public function __construct()
    {
        $link = new Link();
        Tools::redirectAdmin($link->getAdminLink("AdminModules") . "&configure=tafcfpay");
        parent::__construct();
    }
}
