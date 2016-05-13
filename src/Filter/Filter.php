<?php
namespace Ice\Filter;
class Filter {
    protected $config       = array();
    protected $strictMode   = FALSE;

    protected $defaultMap   = array();
    protected $defaultArr   = array();
    protected $defaultInt   = 0;
    protected $defaultFloat = 0.0;
    protected $defaultBool  = FALSE;
    protected $defaultStr   = '';

    protected $extendConfig;

    public function __construct($config, $strictMode = FALSE) {
        $this->config     = $config;
        $this->strictMode = $strictMode;
        if (isset($this->config['extend_path'])) {
            $this->extendConfig = new \F_Config($this->config['extend_path']);
        } else {
            $this->extendConfig = new \U_Stub();
        }
    }

    public function expectData($expectData, $data) {
        return $this->_expectData($expectData, $data);
    }

    protected function _expectData(&$expectData, $data) {
        foreach ($expectData as $k => $v) {
            if (!array_key_exists($k, $data)) {
                unset($expectData[$k]);
                continue;
            } else if (is_array($v)) {
                $this->_expectData($expectData[$k], $data[$k]);
            } else {
                $expectData[$k] = $data[$k];
            }
        }
        return $expectData;
    }

    public function extend_filter(&$data, &$expectData, $filter) {
        $extendSrcCode = $this->extendConfig->get($filter);
        if (empty($extendSrcCode)) {
            return ;
        }
        $proxy   = \F_Ice::$ins->workApp->proxy_filter->get($extendSrcCode, $this->strictMode);
        $tmpData = $proxy->filter($data, $expectData);
        if (is_array($data)) {
            $data = array_merge($data, (array)$tmpData);
        } else if ($data instanceof \U_Map) {
            $data->merge($tmpData);
        }
    }

    public function type_str(&$data, $req = '__opt') {
        if (!isset($data)) {
            if ($req === '__req') return $this->reportMessage();
            else if ($req === '__opt') return TRUE;
            else return ($data = $req) || TRUE;
        }
        $data = (string)$data;
    }
    public function type_int(&$data, $req = '__opt') {
        if (!isset($data)) {
            if ($req === '__req') return $this->reportMessage();
            else if ($req === '__opt') return TRUE;
            else return ($data = $req) || TRUE;
        }
        $data = (int)$data;
    }
    public function type_float(&$data, $req = '__opt') {
        if (!isset($data)) {
            if ($req === '__req') return $this->reportMessage();
            else if ($req === '__opt') return TRUE;
            else return ($data = $req) || TRUE;
        }
        $data = (float)$data;
    }
    public function type_map(&$data, $req = '__opt') {
        if (!isset($data)) {
            if ($req === '__req') return $this->reportMessage();
            else if ($req === '__opt') return TRUE;
            else return ($data = $req) || TRUE;
        }
        if (!($data instanceof \U_Map)) {
            $data = new \U_Map($data);
        }
    }
    public function type_arr(&$data, $req = '__opt') {
        if (!isset($data)) {
            if ($req === '__req') return $this->reportMessage();
            else if ($req === '__opt') return TRUE;
            else return ($data = $req) || TRUE;
        }
        $data = (array)$data;
    }
    public function type_bool(&$data, $req = '__opt') {
        if (!isset($data)) {
            if ($req === '__req') return $this->reportMessage();
            else if ($req === '__opt') return TRUE;
            else return ($data = $req) || TRUE;
        }
        $data = (bool)$data;
    }

    public function op_range(&$data, $range) {
        if (!isset($data)) {
            return TRUE;
        }
        if (!is_numeric($data)) {
            return $this->reportMessage();
        }
        $rangeEles = explode(',', $range);
        foreach ($rangeEles as $ele) {
            $rangeNums = explode('_', $ele);
            $nNums = count($rangeNums);
            if ($nNums < 1 || $nNums > 2) {
                return $this->reportMessage();
            
            } else if ($nNums === 1) {
                if (!is_numeric($rangeNums[0])) {
                    return $this->reportMessage();
                } else if ($data == $rangeNums[0]) {
                    return TRUE;
                }
            } else if ($nNums === 2) {
                if (strlen($rangeNums[0]) === 0 && is_numeric($rangeNums[1]) && $data <= $rangeNums[1]) {
                    return TRUE;
                } else if (strlen($rangeNums[1]) === 0 && is_numeric($rangeNums[0]) && $data >= $rangeNums[0]) {
                    return TRUE;
                } else if (!is_numeric($rangeNums[0]) || !is_numeric($rangeNums[1])) {
                    return $this->reportMessage();
                } else if ($data >= $rangeNums[0] && $data <= $rangeNums[1]) {
                    return TRUE;
                }
            }
        }
        return $this->reportMessage();

    }

    public function op_match(&$data, $pattern) {
        if (!isset($data)) {
            return TRUE;
        }
        switch ($pattern) {
            case 'email':
                $pattern = '/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix';
                break;
            default:
                break;
        }
        if (!preg_match($pattern, $data)) {
            return $this->reportMessage();
        }
    }

    public function op_strip(&$datas) {
        $argv = func_get_args();
        array_shift($argv);
        foreach ($argv as $arg) {
            unset($datas[$arg]);
        }
    }

    protected function reportMessage($message = '') {
        $bt = debug_backtrace();
        $op = substr(@$bt[1]['function'], 3);
        \F_Ice::$ins->mainApp->logger_comm->warn(array(
            'op' => $op,
        ), \F_ECode::FILTER_RUN_STRICT_UNEXPECT, 1);
        if ($this->strictMode) {
            throw new RunException($op, $message);
        } else {
            return FALSE;
        }
    }
}