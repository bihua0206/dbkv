<?php

/**
 * Models User Data
 * @package KBN
 * @author Liulikang (lliu@watercooler-inc.com)
 */
class Model_User_Data {

    const
            VERSION         = 1;

    protected static function getExpireTime() {
        $expire     = defined('MODEL_USER_DATA_EXPIRE') ? intval(MODEL_USER_DATA_EXPIRE) : 86400;
        return  $expire;
    }

    public static function get($playerId, $key) {

        $cacheKey   = self::getCacheKey($playerId, $key);
        $memcache   = self::getMemcache();

        $result     = $memcache->get($cacheKey);

        if (is_array($result) && isset($result['value'])) {

            $ret    = $result['value'];

        } else {

            $value  = DB_User_Data::get($playerId, $key);
            $result = array(
                        'value'     => $value,
                    );
            $memcache->set($cacheKey, $result, $expire = self::getExpireTime());

            $ret    = $value;

        }

        return  $ret;

    }

    public static function set($playerId, $key, $value) {

        $cacheKey   = self::getCacheKey($playerId, $key);
        $memcache   = self::getMemcache();

        $value  = DB_User_Data::set($playerId, $key, $value);
        $result = array(
                    'value'     => $value,
                );
        $memcache->set($cacheKey, $result, $expire = self::getExpireTime());

        return  $value;

    }

    public static function getCacheKey($playerId, $key) {
        global  $serverId;
        $key        = sprintf('model_user_data_s%d_u%d_k%s_slot105', $serverId, $playerId, $key);
        return      $key;
    }

    public static function getMemcache() {
        global  $memcache;
        return  $memcache;
    }

}


