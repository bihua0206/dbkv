<?php

class DB_User_DataConfig {

    const   
            VERSION             = 0;

    protected static $config    = array();
    
    // 设置配置条目
    public static function getConfig() {
        
        if (empty(self::$config)) {
            
            // fields   = Alpha/Beta/Gamma/Delta
            // value    = 'field,offset,length'
            // field    = 数据库的字段名
            // offset   = 起始位，每个配置必须是递增的，this.offset = last(offset + length)
            
            // 正式版的配置
            self::$config   = array(
                            'UCONF_ALLIANCE_TELEPORT_SENT'  => 'Alpha,0,1',
                            'UCONF_RATER_PASSIVE_CLOSED'    => 'Alpha,1,1',
                            /*
                            // following are samples:
                            'UCONF_TEST_2'                  => 'Alpha,2,1',
                            'UCONF_TEST_3'                  => 'Alpha,3,1',
                            */
                            );


        }
        
        return  self::$config;
        
    }

}


