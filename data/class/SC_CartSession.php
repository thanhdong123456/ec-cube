<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2007 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

/* カートセッション管理クラス */
class SC_CartSession {
    var $key;
    var $key_tmp;	// ユニークIDを指定する。

    /* コンストラクタ */
    function SC_CartSession($key = 'cart') {
        SC_Utils::sfDomainSessionStart();

        if($key == "") $key = "cart";
        $this->key = $key;
    }

    // 商品購入処理中のロック
    function saveCurrentCart($key_tmp) {
        $this->key_tmp = "savecart_" . $key_tmp;
        // すでに情報がなければ現状のカート情報を記録しておく
        if(count($_SESSION[$this->key_tmp]) == 0) {
            $_SESSION[$this->key_tmp] = $_SESSION[$this->key];
        }
        // 1世代古いコピー情報は、削除しておく
        foreach($_SESSION as $key => $val) {
            if($key != $this->key_tmp && ereg("^savecart_", $key)) {
                unset($_SESSION[$key]);
            }
        }
    }

    // 商品購入中の変更があったかをチェックする。
    function getCancelPurchase() {
        $ret = isset($_SESSION[$this->key]['cancel_purchase'])
            ? $_SESSION[$this->key]['cancel_purchase'] : "";
        $_SESSION[$this->key]['cancel_purchase'] = false;
        return $ret;
    }

    // 購入処理中に商品に変更がなかったかを判定
    function checkChangeCart() {
        $change = false;
        $max = $this->getMax();
        for($i = 1; $i <= $max; $i++) {
            if ($_SESSION[$this->key][$i]['quantity'] != $_SESSION[$this->key_tmp][$i]['quantity']) {
                $change = true;
                break;
            }
            if ($_SESSION[$this->key][$i]['id'] != $_SESSION[$this->key_tmp][$i]['id']) {
                $change = true;
                break;
            }
        }
        if ($change) {
            // 一時カートのクリア
            unset($_SESSION[$this->key_tmp]);
            $_SESSION[$this->key]['cancel_purchase'] = true;
        } else {
            $_SESSION[$this->key]['cancel_purchase'] = false;
        }
        return $_SESSION[$this->key]['cancel_purchase'];
    }

    // 次に割り当てるカートのIDを取得する
    function getNextCartID() {
        foreach($_SESSION[$this->key] as $key => $val){
            $arrRet[] = $_SESSION[$this->key][$key]['cart_no'];
        }
        return (max($arrRet) + 1);
    }

    /**
     * 商品ごとの合計価格
     * XXX 実際には、「商品」ではなく、「カートの明細行(≒商品規格)」のような気がします。
     *
     * @param integer $id
     * @return string 商品ごとの合計価格(税込み)
     */
    function getProductTotal($id) {
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            if(isset($_SESSION[$this->key][$i]['id'])
               && $_SESSION[$this->key][$i]['id'] == $id) {

                // 税込み合計
                $price = $_SESSION[$this->key][$i]['price'];
                $quantity = $_SESSION[$this->key][$i]['quantity'];
                $pre_tax = SC_Helper_DB_Ex::sfPreTax($price);
                $total = $pre_tax * $quantity;
                return $total;
            }
        }
        return 0;
    }

    // 値のセット
    function setProductValue($id, $key, $val) {
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            if(isset($_SESSION[$this->key][$i]['id'])
               && $_SESSION[$this->key][$i]['id'] == $id) {
                $_SESSION[$this->key][$i][$key] = $val;
            }
        }
    }

    // カート内商品の最大要素番号を取得する。
    function getMax() {
        $cnt = 0;
        $pos = 0;
        $max = 0;
        if (count($_SESSION[$this->key]) > 0){
            foreach($_SESSION[$this->key] as $key => $val) {
                if (is_numeric($key)) {
                    if($max < $key) {
                        $max = $key;
                    }
                }
            }
        }
        return ($max);
    }

    // カート内商品数の合計
    function getTotalQuantity() {
        $total = 0;
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            $total+= $_SESSION[$this->key][$i]['quantity'];
        }
        return $total;
    }


    // 全商品の合計価格
    function getAllProductsTotal() {
        // 税込み合計
        $total = 0;
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {

            if (!isset($_SESSION[$this->key][$i]['price'])) {
                $_SESSION[$this->key][$i]['price'] = "";
            }
            $price = $_SESSION[$this->key][$i]['price'];

            if (!isset($_SESSION[$this->key][$i]['quantity'])) {
                $_SESSION[$this->key][$i]['quantity'] = "";
            }
            $quantity = $_SESSION[$this->key][$i]['quantity'];

            $pre_tax = SC_Helper_DB_Ex::sfPreTax($price);
            $total+= ($pre_tax * $quantity);
        }
        return $total;
    }

    // 全商品の合計税金
    function getAllProductsTax() {
        // 税合計
        $total = 0;
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            $price = $_SESSION[$this->key][$i]['price'];
            $quantity = $_SESSION[$this->key][$i]['quantity'];
            $tax = SC_Helper_DB_Ex::sfTax($price);
            $total+= ($tax * $quantity);
        }
        return $total;
    }

    // 全商品の合計ポイント
    function getAllProductsPoint() {
        // ポイント合計
        $total = 0;
        if (USE_POINT !== false) {
            $max = $this->getMax();
            for($i = 0; $i <= $max; $i++) {
                $price = $_SESSION[$this->key][$i]['price'];
                $quantity = $_SESSION[$this->key][$i]['quantity'];

                if (!isset($_SESSION[$this->key][$i]['point_rate'])) {
                    $_SESSION[$this->key][$i]['point_rate'] = "";
                }
                $point_rate = $_SESSION[$this->key][$i]['point_rate'];

                if (!isset($_SESSION[$this->key][$i]['id'][0])) {
                    $_SESSION[$this->key][$i]['id'][0] = "";
                }
                $id = $_SESSION[$this->key][$i]['id'][0];
                $point = SC_Utils_Ex::sfPrePoint($price, $point_rate, POINT_RULE, $id);
                $total+= ($point * $quantity);
            }
        }
        return $total;
    }

    // カートへの商品追加
    function addProduct($id, $quantity, $campaign_id = "") {
        $find = false;
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {

            if($_SESSION[$this->key][$i]['id'] == $id) {
                $val = $_SESSION[$this->key][$i]['quantity'] + $quantity;
                if(strlen($val) <= INT_LEN) {
                    $_SESSION[$this->key][$i]['quantity']+= $quantity;
                    if(!empty($campaign_id)){
                        $_SESSION[$this->key][$i]['campaign_id'] = $campaign_id;
                        $_SESSION[$this->key][$i]['is_campaign'] = true;
                    }
                }
                $find = true;
            }
        }
        if(!$find) {
            $_SESSION[$this->key][$max+1]['id'] = $id;
            $_SESSION[$this->key][$max+1]['quantity'] = $quantity;
            $_SESSION[$this->key][$max+1]['cart_no'] = $this->getNextCartID();
            if(!empty($campaign_id)){
                $_SESSION[$this->key][$max+1]['campaign_id'] = $campaign_id;
                $_SESSION[$this->key][$max+1]['is_campaign'] = true;
            }
        }
    }

    // 前頁のURLを記録しておく
    function setPrevURL($url) {
        // 前頁として記録しないページを指定する。
        $arrExclude = array(
            "/shopping/"
        );
        $exclude = false;
        // ページチェックを行う。
        foreach($arrExclude as $val) {
            if(ereg($val, $url)) {
                $exclude = true;
                break;
            }
        }
        // 除外ページでない場合は、前頁として記録する。
        if(!$exclude) {
            $_SESSION[$this->key]['prev_url'] = $url;
        }
    }

    // 前頁のURLを取得する
    function getPrevURL() {
        return isset($_SESSION[$this->key]['prev_url'])
            ? $_SESSION[$this->key]['prev_url'] : "";
    }

    // キーが一致した商品の削除
    function delProductKey($keyname, $val) {
        $max = count($_SESSION[$this->key]);
        for($i = 0; $i < $max; $i++) {
            if($_SESSION[$this->key][$i][$keyname] == $val) {
                unset($_SESSION[$this->key][$i]);
            }
        }
    }

    function setValue($key, $val) {
        $_SESSION[$this->key][$key] = $val;
    }

    function getValue($key) {
        return $_SESSION[$this->key][$key];
    }

    function getCartList() {
        $max = $this->getMax();
        $arrRet = array();
        for($i = 0; $i <= $max; $i++) {
            if(isset($_SESSION[$this->key][$i]['cart_no'])
               && $_SESSION[$this->key][$i]['cart_no'] != "") {
                $arrRet[] = $_SESSION[$this->key][$i];
            }
        }
        return $arrRet;
    }

    // カート内にある商品ＩＤを全て取得する
    function getAllProductID() {
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            if($_SESSION[$this->key][$i]['cart_no'] != "") {
                $arrRet[] = $_SESSION[$this->key][$i]['id'][0];
            }
        }
        return $arrRet;
    }

    function delAllProducts() {
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            unset($_SESSION[$this->key][$i]);
        }
    }

    // 商品の削除
    function delProduct($cart_no) {
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            if($_SESSION[$this->key][$i]['cart_no'] == $cart_no) {
                unset($_SESSION[$this->key][$i]);
            }
        }
    }

    // 数量の増加
    function upQuantity($cart_no) {
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            if($_SESSION[$this->key][$i]['cart_no'] == $cart_no) {
                if(strlen($_SESSION[$this->key][$i]['quantity'] + 1) <= INT_LEN) {
                    $_SESSION[$this->key][$i]['quantity']++;
                }
            }
        }
    }

    // 数量の減少
    function downQuantity($cart_no) {
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            if($_SESSION[$this->key][$i]['cart_no'] == $cart_no) {
                if($_SESSION[$this->key][$i]['quantity'] > 1) {
                    $_SESSION[$this->key][$i]['quantity']--;
                }
            }
        }
    }

    /**
     * カートの中のキャンペーン商品のチェック
     * @param integer $campaign_id キャンペーンID
     * @return boolean True:キャンペーン商品有り False:キャンペーン商品無し
     */
    function chkCampaign($campaign_id){
        $max = $this->getMax();
        for($i = 0; $i <= $max; $i++) {
            if($_SESSION[$this->key][$i]['is_campaign'] and $_SESSION[$this->key][$i]['campaign_id'] == $campaign_id) return true;
        }

        return false;
    }

}
?>
