<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Application,
    Bitrix\Main\Context,
    Bitrix\Main\Request,
    Bitrix\Main\Server;

$context = Application::getInstance()->getContext();
$request = $context->getRequest();
$values = $request->getPostList();

CModule::IncludeModule("catalog");

/*
 * запрос должен содержать 3 основных поля:
 * login, passwd - для авторизации
 * и body с основным телом запроса (остатки товаров по складам)
 * */

if (isset($values['login']) && isset($values['passwd'])) {
    global $USER, $APPLICATION;
    if (!is_object($USER)) $USER = new CUser;
    $arAuthResult = $USER->Login($values['login'], $values['passwd']);
    $APPLICATION->arAuthResult = $arAuthResult;
    if (isset($arAuthResult['TYPE']) && $arAuthResult['TYPE'] == 'ERROR') {
        echo json_encode(["success" => false, "errors" => [$arAuthResult['MESSAGE']]]);
    } else {
        if ($values['body']) {
            $arErrors = [];
            foreach ($values['body'] as $store) {
                $arStore = \Bitrix\Catalog\StoreTable::getById($store["uuid"])->fetch();
                foreach ($store["stocks"] as $product) {
                    if ($arStore) {
                        $filter = ['=PRODUCT_ID' => $product['uuid'], '=STORE_ID' => $store["uuid"]];
                    } else {
                        $filter = ['=PRODUCT_ID' => $product['uuid'], 'STORE.ACTIVE' => 'Y'];
                    }
                    $rsStoreProduct = \Bitrix\Catalog\StoreProductTable::getList(array(
                        'filter' => $filter,
                        'limit' => 1,
                        'select' => array('AMOUNT'),
                    ));
                    if ($arStoreProduct = $rsStoreProduct->fetch()) {
                        $arStoreProduct['AMOUNT'] = $product['quantity'];
                        $ID = CCatalogStoreProduct::Update($arStoreProduct['ID'], $arStoreProduct);
                        if (!is_numeric($ID)) {
                            $arErrors[] = 'Ошибка обновления товара ID=' . $product['uuid'];
                        }
                    } else {
                        $arErrors[] = 'Товар не найден ID=' . $product['uuid'];
                    }
                }
            }
            if(!empty($arErrors)){
                echo json_encode(["success" => false, "errors" => $arErrors]);
            }else{
                echo json_encode(["success" => true]);
            }
        }
    }
} else {
    echo json_encode(["success" => false, "errors" => ['Ошибка авторизации']]);
}