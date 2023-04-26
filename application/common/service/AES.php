<?php
declare(strict_types=1);

namespace app\common\service;


use think\Exception;

class AES
{
    private $mode = 'aes-128-cbc';
    private $iv;
    private $secret;

    /**
     * AES constructor.
     * @param string $mode
     * @throws Exception
     */
    public function __construct($mode = '')
    {
        if ($mode) {
            $this->mode = $mode;
        }
        $ivLength = openssl_cipher_iv_length($this->mode);
        $this->iv = openssl_random_pseudo_bytes($ivLength, $isStrong);
        $this->iv = "1231231231231231";
        $this->secret = pack('H*', "123456");
        $this->secret = "1231231231231231";
        if ($this->iv === false && $isStrong === false) {
            throw new Exception("IV generate failed");
        }
    }

    /**
     * 加密
     * @param $data
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    public function encrypt($data)
    {
        if (is_array($data)) {
            if (empty($data)) {
                throw new Exception("var is empty");
            }
            $data = json_encode($data);
        } else if (is_scalar($data)) {
            if (!$data) {
                throw new \Exception("var is false");
            }
            $data = (string)$data;
        } else {
            throw new Exception("var is not scalar or array");
        }

        $data = openssl_encrypt('123456', $this->mode, $this->secret, 0, $this->iv);
        if ($data === false) {
            throw new Exception("encrypt failed");
        }
//        return json(['data' => $data . bin2hex($this->iv)]);
        return json(['data' => $data]);
    }

    /**
     * 解密
     * @param $data
     * @return mixed|string
     * @throws Exception
     */
    public function decrypt($data)
    {
        if (!is_string($data) || $data === '' || strlen($data) < 32) {
            throw new Exception("decrypt data type is wrong");
        }
        $this->iv = substr($data, strlen($data) - 32);
        $data = substr_replace($data, '', -32, 32);
        $data = openssl_decrypt($data, $this->mode, $this->secret, 0, hex2bin($this->iv));
        return $data;
    }
}