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
                $storeData = Bitrix\Catalog\StoreTable::getList(array(
                    'filter' => array('=XML_ID' => $store["uuid"]),
                    'select' => array('ID'),
                ))->fetch();
                $storeId = $storeData['ID'];
                foreach ($store["stocks"] as $product) {
                    $productId = false;
                    $result = CCatalogProduct::GetList(
                        [],
                        ['=ELEMENT_XML_ID' => $product['uuid']],
                        false,
                        false,
                        ['ID']
                    );
                    while ($ar_res = $result->Fetch()) {
                        if ($ar_res['ID']) {
                            $productId = $ar_res['ID'];
                        }
                    }
                    if ($productId) {
                        if ($storeId) {
                            $filter = ['=PRODUCT_ID' => $productId, '=STORE_ID' => $storeId];
                        } else {
                            $filter = ['=PRODUCT_ID' => $productId, 'STORE.ACTIVE' => 'Y'];
                        }
                        $rsStoreProduct = \Bitrix\Catalog\StoreProductTable::getList(array(
                            'filter' => $filter,
                            'limit' => 1,
                            'select' => array('AMOUNT'),
                        ));
                        if ($arStoreProduct = $rsStoreProduct->fetch()) {
                            $arStoreProduct['AMOUNT'] = $product['quantity'];
                            if (!CCatalogStoreProduct::Update($productId, $arStoreProduct)) {
                                $arErrors[] = 'Ошибка обновления товара ID=' . $productId;
                            }
                        } else {
                            $arErrors[] = 'Товар не найден ID=' . $productId;
                        }
                    } else {
                        $arErrors[] = 'Товар не найден XML_ID=' . $product['uuid'];
                    }
                }
            }
            if (!empty($arErrors)) {
                echo json_encode(["success" => false, "errors" => $arErrors]);
            } else {
                echo json_encode(["success" => true]);
            }
        }
    }
} else {
    echo json_encode(["success" => false, "errors" => ['Ошибка авторизации']]);
}