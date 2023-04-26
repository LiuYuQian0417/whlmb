<?php
declare(strict_types=1);

namespace app\common\service;

use OSS\Core\OssException;
use OSS\OssClient;
use think\facade\Config;

//version 2.3.0

/**
 * 阿里OSS文件管理类
 * Class OSS
 *
 * @package app\common\service
 */
class OSS
{
    //自定义配置,权重更高
    const isSSl = TRUE;
    //oss客户端对象实例
    const ACL = [
        'default',  //继承bucket
        'private',  //私有
        'public-read',  //公有读,私有写
        'public-read-write',    //公有读写
    ];
    //是否为https请求
    const ERROR = [
        'AccessDenied'                    => '拒绝访问',
        'BucketAlreadyExists'             => 'Bucket已经存在',
        'BucketNotEmpty'                  => 'Bucket不为空',
        'EntityTooLarge'                  => '实体过大',
        'EntityTooSmall'                  => '实体过小',
        'FileGroupTooLarge'               => '文件组过大',
        'FilePartNotExist'                => '文件Part不存在',
        'FilePartStale'                   => '文件Part过时',
        'InvalidArgument'                 => '参数格式错误',
        'InvalidAccessKeyId'              => 'AccessKeyId不存在',
        'InvalidBucketName'               => '无效的Bucket名字',
        'InvalidDigest'                   => '无效的摘要',
        'InvalidObjectName'               => '无效的Object名字',
        'InvalidPart'                     => '无效的Part',
        'InvalidPartOrder'                => '无效的part顺序',
        'InvalidTargetBucketForLogging'   => 'Logging操作中有无效的目标bucket',
        'InternalError'                   => 'OSS内部发生错误',
        'MalformedXML'                    => 'XML格式非法',
        'MethodNotAllowed'                => '不支持的方法',
        'MissingArgument'                 => '缺少参数',
        'MissingContentLength'            => '缺少内容长度',
        'NoSuchBucket'                    => 'Bucket不存在',
        'NoSuchKey'                       => '文件不存在',
        'NoSuchUpload'                    => 'Multipart Upload ID不存在',
        'NotImplemented'                  => '无法处理的方法',
        'PreconditionFailed'              => '预处理错误',
        'RequestTimeTooSkewed'            => '发起请求的时间和服务器时间超出15分钟',
        'RequestTimeout'                  => '请求超时',
        'SignatureDoesNotMatch'           => '签名错误',
        'InvalidEncryptionAlgorithmError' => '指定的熵编码加密算法错误',
    ];
    //oss文件读写权限(由高到低)
    private static $instance;
    private $config = [];

    public function __construct()
    {
        //读取系统oss配置文件
        $config = Config::pull('oss');
        if (!$config)$config = require '../../../../../../config/oss.php';

        if ($config)
        {
            $this->config = array_merge($this->config, $config);
        }
        self::setUp();
    }

    /**访问控制**/

    /**
     * 创建实例
     */
    private function setUp()
    {
        try
        {
            self::$instance = new OssClient(
                $this->config['AccessKeyId'],
                $this->config['AccessKeySecret'],
                $this->config['normalEndPoint']
            );
            self::$instance->setConnectTimeout(10); //连接超时
            self::$instance->setTimeout(3600);  //请求超时时间
        } catch (OssException $e)
        {
            die($e->getMessage());
        }
    }

    /**
     * 获取文件的带签名访问地址
     *
     * @param $ossFileName
     *
     * @return array
     * @throws OssException
     */
    public function getSignUrlForGet($ossFileName)
    {
        try
        {
            if (!$ossFileName)
            {
                return ['code' => 0, 'url' => ''];
            }
            $timeout = 3600 * 24;
            if (is_null(self::$instance))
            {
                self::setUp();
            }
            self::$instance->setUseSSL(self::isSSl);
            $signUrl = self::$instance->signUrl($this->config['bucket'], $ossFileName, $timeout);
            return ['code' => 0, 'url' => $signUrl];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 获取文件访问地址
     *
     * @param $ossFileName
     *
     * @return string
     */
    public function getUrlForGet($ossFileName)
    {
        $_fileUrl = '';

        if ($ossFileName)
        {
            $_fileUrl = $this->config['prefix'] . $ossFileName;
        }

        return $_fileUrl;
    }

    /**
     * 字符串上传
     *
     * @param $ossFileName string 上传之后相对根目录的路径
     * @param $content     string 需要保存的字符串
     *
     * @return array
     * @throws OssException
     */
    public function stringUpload($ossFileName, $content)
    {
        try
        {
            self::$instance->putObject($this->config['bucket'], $ossFileName, $content);
            return ['code' => 0];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 本地文件上传
     *
     * @param $ossFileName string 上传之后相对根目录的路径
     * @param $localPath   string 本地文件得绝对路径
     *
     * @return array
     * @throws OssException
     */
    public function fileUpload($ossFileName, $localPath)
    {
        try
        {
            $info = self::$instance->uploadFile($this->config['bucket'], $ossFileName, $localPath);
            return ['code' => 0, 'info' => $info];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 重新设置文件的读写权限
     *
     * @param       $ossFileName string 文件路径
     * @param mixed $acl         string 权限值
     *
     * @return array
     * @throws OssException
     */
    public function putObjectAcl($ossFileName, $acl = '')
    {
        try
        {
            // 默认公有读,私有写
            if ($acl === '')
            {
                $acl = self::ACL[2];
            }
            self::$instance->putObjectAcl($this->config['bucket'], $ossFileName, $acl);
            return ['code' => 0];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 追加上传字符串
     *
     * @param $ossFileName string 上传之后相对根目录的路径
     * @param $contentArr  array 字符串集合上传数据
     *
     * @return array
     * @throws OssException
     */
    public function stringAppendUpload($ossFileName, $contentArr)
    {
        try
        {
            if (!is_array($contentArr))
            {
                return ['code' => -1, 'message' => '上传数据应该为数组'];
            }
            $position = 0;  //文件追加偏移位置
            foreach ($contentArr as $item)
            {
                if (!is_string($item))
                {
                    return ['code' => -2, 'message' => '上传数据内容应该为字符串'];
                }
                $position = self::$instance->appendObject($this->config['bucket'], $ossFileName, $item, $position);
            }
            return ['code' => 0];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 追加上传本地文件
     *
     * @param $ossFileName  string 上传之后相对根目录的路径
     * @param $localPathArr array 字符串集合上传数据
     *
     * @return array
     * @throws OssException
     */
    public function fileAppendUpload($ossFileName, $localPathArr)
    {
        try
        {
            if (!is_array($localPathArr))
            {
                return ['code' => -1, 'message' => '上传数据应该为数组'];
            }
            $position = 0;  //文件追加偏移位置
            foreach ($localPathArr as $item)
            {
                if (!is_string($item))
                {
                    return ['code' => -2, 'message' => '上传数据内容应该为字符串'];
                }
                $position = self::$instance->appendFile($this->config['bucket'], $ossFileName, $item, $position);
            }
            return ['code' => 0];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 分片上传本地文件[使用场景:文件超大(>100M),网络不稳定,断点上传,无法确定文件大小]
     *
     * @param $ossFileName string 上传之后相对根目录的路径
     * @param $localPath   string 本地文件得绝对路径
     *
     * @return array
     * @throws OssException
     */
    public function multiFileUpload($ossFileName, $localPath)
    {
        try
        {
            self::$instance->multiuploadFile($this->config['bucket'], $ossFileName, $localPath);
            return ['code' => 0];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**管理[列表,下载,删除]文件**/

    /**
     * 分片上传本地文件目录
     *
     * @param $ossDir   string 上传之后相对根目录的路径
     * @param $localDir string 本地目录的绝对路径或者相对脚本的路径
     *
     * @return array
     * @throws OssException
     */
    public function multiDirUpload($ossDir, $localDir)
    {
        try
        {
            self::$instance->uploadDir($this->config['bucket'], $ossDir, $localDir);
            return ['code' => 0];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 枚举oss目录文件
     *
     * @param $dir        string oss上的目录
     * @param $limit      integer 限制取出的数量,默认100,最大不能超过1000
     * @param $nextMarker string
     *
     * @return array
     * @throws OssException
     */
    public function listObject($dir, $limit, $nextMarker = '')
    {
        try
        {
            $options = [
                'prefix'    => $dir,
                'delimiter' => '/',
                'max-keys'  => $limit,
                'marker'    => $nextMarker,
            ];
            $listInfo = self::$instance->listObjects($this->config['bucket'], $options);
            $list = $listInfo->getObjectList();
            $listArr = [];
            if ($list)
            {
                foreach ($list as $item)
                {
                    $listArr[] = [
                        'url'          => $item->getKey(),
                        'size'         => round($item->getSize() / 1024, 3) . ' KB',  //返回KB
                        'lastModified' => $item->getLastModified(),
                    ];
                }
            }
            return ['code' => 0, 'data' => $listArr];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 下载oss文件到本地或内存
     *
     * @param $ossFileName string 上传之后相对根目录的路径
     * @param $localPath   string 将要存储的文件绝对路径
     * @param $target      integer 存储类型 0 内存 1 文件
     * @param $range       string 下载的范围,'0-100',返回第0到第100个字节的数据,包括第100个,共101字节的数据
     *
     * @return array
     * @throws OssException
     */
    public function downloadToLocal($ossFileName, $localPath = '', $target = 1, $range = '')
    {
        try
        {
            //检测文件是否存在
            if (!self::isExist($ossFileName))
            {
                return ['code' => -1, 'message' => '文件不存在'];
            }
            $options = [
                OssClient::OSS_FILE_DOWNLOAD => $localPath,
                OssClient::OSS_RANGE         => $range,
            ];
            if (!$target)
            {
                $options = [];
            }
            self::$instance->getObject($this->config['bucket'], $ossFileName, $options);
            return ['code' => 0, 'message' => '下载成功'];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 检测oss文件是否存在
     *
     * @param $ossFileName string 上传之后相对根目录的路径
     *
     * @return array
     * @throws OssException
     */
    public function isExist($ossFileName)
    {
        try
        {
            $exist = self::$instance->doesObjectExist($this->config['bucket'], $ossFileName);
            return ['code' => $exist ? 0 : 1];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 单删或多删oss文件[不存在的文件自动忽略]
     *
     * @param $ossFileName string 上传之后相对根目录的路径
     *
     * @return array
     * @throws OssException
     */
    public function deleteFile($ossFileName)
    {
        try
        {
            if (is_string($ossFileName))
            {
                self::$instance->deleteObject($this->config['bucket'], $ossFileName);
            }
            if (is_array($ossFileName))
            {
                self::$instance->deleteObjects($this->config['bucket'], $ossFileName);
            }
            return ['code' => 0, 'message' => '删除成功'];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    /**
     * 复制文件[拷贝小于1GB的文件]
     *
     * @param $fromObject string 原oss文件地址
     * @param $toObject   string 目标oss文件地址
     *
     * @return array
     * @throws OssException
     */
    public function copyObject($fromObject, $toObject)
    {
        try
        {
            $fromBucket = $toBucket = $this->config['bucket'];
            self::$instance->copyObject($fromBucket, $fromObject, $toBucket, $toObject);
            return ['code' => 0, 'message' => '复制成功'];
        } catch (OssException $e)
        {
            throw new OssException($e->getMessage());
        }
    }

    public function __destruct()
    {
        self::$instance = NULL;
    }
}