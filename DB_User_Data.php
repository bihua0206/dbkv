<?php

class DB_User_Data {

    const   

            TABLE_NAME          = 'userData',

            // 最大支持 63 个项目的 bool 值
            MAX_BITS            = 63,

            VERSION             = 0;


    
    // 更新到 DB
    public static function set($intUID, $strKey, $intValue) {
        
        $arrRes     = self::getLogicValue($strKey, $intValue);
        $arrSQL     = $arrRes['sql'];
        $field      = $arrRes['field'];
        
        $arrSET     = array();
        
        foreach ($arrSQL as $oneLogic) {
            $arrSET[]   = sprintf(" %s = %s %s %d ", $field, $field, $oneLogic[0], $oneLogic[1]);
        }
        
        $strSQL     = sprintf("UPDATE %s SET %s WHERE userId = %d ", 
                                self::TABLE_NAME, implode(',', $arrSET), $intUID
                             );

        try {
        
            $result     = DB_Base::auxExec($strSQL);

            $ret        = $intValue;

        } catch (Exception $exp) {

            print_r($exp);

            $ret        = false;

        }
        
        return  $ret;
        
    }
    
    /**
     * 从 DB 读取数值
     *
     * @return int      如果失败（没有记录或 DB 错误），返回 false
     */
    public static function get($intUID, $key) {
        
        $config     = DB_User_DataConfig::getConfig();
        
        $ret        = false;
        
        // 确保 key 已经配置
        if (!isset($config[$key])) {
            $msg        = sprintf('userData[%s] is not defined (getting)', $key);
            Model_Error_Handle::throwOut($msg, 4, Model_Error_Conf::EXP_DB_USER_DATA);
        }
            
        $explode    = explode(',', $config[$key]);
        $field      = $explode[0];
        $offset     = (int) $explode[1];
        $length     = (int) $explode[2];
        
        $strSQL     = sprintf("SELECT * FROM %s WHERE userId = %d ", self::TABLE_NAME, $intUID);
        $result     = DB_Base::auxQueryOne($strSQL);

        // 确保该字段有值
        if (isset($result[$field])) {
            
            $value      = (int) $result[$field];
            
            // 把 offset 右边的所有 bits 都清空
            $value      = $value >> $offset;
            
            // 把 length 个 bits 置为 1，然后对 value 做 AND
            $mask       = (1 << ($length)) - 1; 
            
            // 得到的结果就是 offset 的十进制数值
            $ret        = (int) ($value & $mask);
            
        }
            
        return  $ret;
        
    }
      
    /**
     * 计算用于 SQL 语句的逻辑操作符和操作数
     *
     * @return array        {1,2} 条 SQL 指令
     */
    public static function getLogicValue($key, $value = 0) {
        
        $config     = DB_User_DataConfig::getConfig();

        if (!isset($config[$key])) {
            $msg        = sprintf('userData[%s] is not defined (setting)', $key);
            Model_Error_Handle::throwOut($msg, 2, Model_Error_Conf::EXP_DB_USER_DATA);
        }
        
        $explode    = explode(',', $config[$key]);
        $field      = $explode[0];
        $offset     = (int) $explode[1];
        $length     = (int) $explode[2];
        
        if (($offset + $length) > self::MAX_BITS) {
            throw new Exception(
                                    sprintf("user config exceed max bits[%d], offset=%d&length=%d", 
                                                self::MAX_BITS, $offset, $length
                                            )
                                );
        }
        
        if (1 == $length) {
            
            // 为了减少性能损耗，如果 length = 1，无论 value 是 0/1 都可以凭单条 SQL 进行更新
            $SQL    = self::calcSingle($value, $offset);
            
        } else {
            
            // 如果 offset > 1，那么最多需要 2 条 SQL 更新，一条置 0，另一条置 value
            $SQL    = self::calcMulti($value, $offset, $length);
            
        }
        
        $arrRet = array(
                    'field'     => $field,
                    'offset'    => $offset,
                    'length'    => $length,
                    'sql'       => $SQL,
                );
        
        return  $arrRet;
        
    }
    
    /**
     * 针对 length = 1 的 set 操作
     *
     * @return array    count = 1
     */
    protected static function calcSingle($value, $offset) {
        
        $length     = 1;
        
        $arrRet     = array();
        
        // 余数一定是 [0,1]
        $value      = $value ? 1 : 0;
        
        // 首先做 set value = 0
        // set 时做逻辑 AND 操作，目标 bit AND 0，右边剩余 bits OR 1
        // Step-1 目标 bit AND 0：      1 左移 offset + length
        // Step-2 右边剩余 bits OR 1：  如果有 offset 就把 1 左移 offset 并 -1，否则啥也不做
        
        if ($value) {
            // set value = 1
            // set 时做逻辑 OR 操作，目标 bit OR 1，右边其余 bits OR 0
            $logic      = '|';
            $value      = 1 << $offset;
            
        } else {
            // set value = 0
            // set 时做逻辑 AND 操作，目标 bit AND 0，右边剩余 bits OR 1
        
            $logic      = '&';
            $value      = 0X7FFFFFFFFFFFFFFF ^ (1 << $offset);
            
        }
        
        $arrRet[]   = array($logic, $value);
        
        return  $arrRet;
        
    }
    
    /**
     * 针对 length > 1 的 set 操作
     *
     * @return array    count = {1,2}   
                        set value = 0   时返回 1 条
                        set value != 0  时返回 2 条
     */
    protected static function calcMulti($value, $offset, $length) {
        
        $arrRet     = array();
        
        // 对 length 取模，余数一定是 0 ~ (2^Len - 1)
        $value      = $value % pow(2, $length);
        
        // 首先做 set value = 0
        // set 时做逻辑 AND 操作，目标 bit AND 0，右边剩余 bits OR 1
        // Step-1 目标 bit AND 0：      1 左移 offset + length
        // Step-2 右边剩余 bits OR 1：  如果有 offset 就把 1 左移 offset 并 -1，否则啥也不做
        $logic      = '&';
        $setZero    = (1 << ($offset + $length)) + ($offset ? ((1 << $offset) - 1) : 0);
        $arrRet[]   = array($logic, $setZero);
        
        // 如果 value > 0，则还要添加一个 SET 语句，把最终值 OR 到指定的 offset
        if ($value) {
            // 认为 set value = (num > 0)
            // set 时做逻辑 OR 操作，目标 bits OR num，右边其余 bits OR 0
            $logic      = '|';
            $value      = $value << $offset;
            $arrRet[]   = array($logic, $value);
            
        }
        
        return  $arrRet;
        
    }

}


