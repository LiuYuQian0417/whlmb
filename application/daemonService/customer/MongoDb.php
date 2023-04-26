<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/25 0025
 * Time: 18:20
 */

namespace app\daemonService\customer;

use \MongoDB\Driver\
{
    Manager,     //连接实例
    BulkWrite,   //写入实例
    Query,        //查询实例
    Command        //执行
};
use think\Exception;
use think\facade\Config;

class MongoDb
{
    private $port = '27017';              //端口

    private $username = 'admin';          //用户名

    private $password = 'admin';          //密码

    private $localhost = '127.0.0.1';     //主机

    private $db = 'ishop_dev';     //数据库名字

    private $is_auth = FALSE;               //是否需要验证身份

    private $collection = '';             //当前操作集合名字

//    private $_collection = [
//        'MESSAGE',             //客服消息集合
//    ];         //集合名字


    private $BulkWrite = [];              //保存当前写操作数据指令集合

    public $instanceArr = [];               //对象实例集合

    public $_option = [];                   //查询选项

    private $_all_option = [
        'limit',
        'batchSize',
        'skip',
        'sort',
        'modifiers',
        'projection',
        'tailable',
        'slaveOk',
        'oplogReplay',
        'noCursorTimeout',
        'awaitData',
        'exhaust',
        'partial',
    ];           //所有查询选项


    public function __construct()
    {
        $config = Config::pull('mongo_db');
        $this->is_auth = (bool)($config['is_auth'] ?? $this->is_auth);
        $this->username = $config['username'] ?? $this->username;
        $this->password = $config['password'] ?? $this->password;
        $this->localhost = $config['localhost'] ?? $this->localhost;
        $this->port = $config['port'] ?? $this->port;
        $this->db = $config['db'] ?? $this->db;
    }

    /**
     * 获得数据库实例
     * @return mixed
     */
    public function getManager()
    {
        $Manager = $this->instanceArr['Manager'] ?? '';
        if (!$Manager instanceof Manager)
        {
            $this->instanceArr['Manager'] = new Manager(
                'mongodb://' . ($this->is_auth ? "{$this->username}:{$this->password}@" : '') . $this->localhost . ':' . $this->port
            );
        }
        return $this->instanceArr['Manager'];
    }

    /**
     * 清除选项
     * @param bool $option
     * @return mixed
     */
    public function removeOption($option = TRUE)
    {
        if ($option == TRUE)
        {
            $this->_option = [];
        } else
        {
            foreach ($option as $v)
            {
                if (isset($this->_option[$v]))
                {
                    unset($this->_option[$v]);
                }
            }
        }
        return $this;

    }


    /**
     * 设置查询的字段
     * @param array $field 字段
     * @param bool $filtration true是返回false是过滤
     * @return MongoDb
     */
    public function field($field, $filtration = TRUE)
    {
        $field_coutn = count($field);
        $_filtration = [];
        for ($i = 0; $i < $field_coutn; $i++)
        {
            $_filtration[] = (int)$filtration;
        }
        $this->_option['projection'] = array_combine($field, $_filtration);
        return $this;
    }

    /**
     * 对查询结果进行排序
     * @param array $sort asc 升序 desc 降序
     * @return MongoDb
     */
    public function sort($sort = [])
    {
        $_sort = ['asc' => 1, 'desc' => -1];
        foreach ($sort as &$v)
        {
            if (isset($_sort[$v]))
            {
                $v = $_sort[$v];
            } else
            {
                unset($v);
            }
        }
        $this->_option['sort'] = $sort;
        return $this;
    }

    /**
     * 设置查询条件
     * @param array $where
     * @return MongoDb
     */
    public function where(array $where = [])
    {
        $this->_option['where'] = $where;
        return $this;
    }

    /**
     * 指定跳过几个数据
     * @param int $skip
     * @return MongoDb
     */
    public function skip(int $skip = 0)
    {
        $this->_option['skip'] = $skip;
        return $this;
    }

    /**
     * 设置返回查询数量
     * @param int $limit
     * @return MongoDb
     */
    public function limit(int $limit = 0)
    {
        $this->_option['limit'] = $limit;
        return $this;
    }

    /**
     * 添加插入操作到指令集和
     * @param array|object $document 插入的文档
     * @param bool $is_auto_id id是否自增
     * @return MongoDb
     */
    public function insert(array $document, $is_auto_id = TRUE)
    {
        foreach ($document as $v)
        {
            if ($is_auto_id)
            {
                $v['id'] = $this->getNextId('ids');
            }
            $this->BulkWrite['insert'][] = $v;
        }
        unset($document);
        return $this;

    }

    /**
     * 添加更新操作到指令集和
     * @param array $data 更新数据条件
     * @param bool $multi 是否只更新一条数据
     * @param bool $upsert 是否
     * @return MongoDb
     */
    public function update(array $data, bool $multi = TRUE, bool $upsert = FALSE)
    {
        $this->BulkWrite['update'][] = [
            $this->_option['where'] ?? [],
            $data,
            ['multi' => $multi, 'upsert' => $upsert],
        ];
        //清空更新条件
        $this->removeOption(['where']);
        return $this;

    }

    /**
     * 添加删除操作到指令集和
     * @param bool $limit 删除匹配所有项目
     * @return MongoDb
     */
    public function delete($limit = FALSE)
    {
        $this->BulkWrite['delete'][] = [$this->_option['where'] ?? [], ['limit' => $limit]];
        //清空删除条件
        $this->removeOption(['where']);
        return $this;

    }

    /**
     * 执行数据操作
     * @param bool $ordered 命令是否按照顺序执行
     * @return mixed
     * @throws Exception
     */
    public function save($ordered = TRUE)
    {
        if ($this->collection == '')
        {
            throw new Exception('无效的集合选取');
        }
        $bulk = new BulkWrite(['ordered' => $ordered]);
        foreach ($this->BulkWrite as $b_k => $b_v)
        {
            foreach ($b_v as $c_v)
            {
                switch ($b_k)
                {
                    case 'insert':
                        $bulk->insert($c_v);
                        break;
                    case 'update':
                        $bulk->update($c_v[0], $c_v[1], $c_v[2]);
                        break;
                    case 'delete':
                        $bulk->delete($c_v[0], $c_v[1]);
                        break;
                }
            }
        }
        //执行数据操作
        $res = $this->getManager()->executeBulkWrite($this->db . '.' . $this->collection, $bulk);
        //清空数据操作
        $this->BulkWrite = [];
        return $res;
    }

    /**
     * 执行查询操作
     * @param string $collection 集合名字
     * @param array $projection 指定操作模式
     * @return Query
     * @throws Exception
     */
    public function query($collection, array $projection = [])
    {
        $_opetion = [];
        foreach (['projection', 'sort', 'limit', 'skip'] as $v)
        {
            if (isset($this->_option[$v]))
            {
                $projection[$v] = $this->_option[$v];
            }
        }
        foreach ($_opetion as $k => $v)
        {
            if (!in_array($k, $this->_all_option))
            {
                throw new Exception('错误的查询选项:' . $k);
            }
        }
        $where = $this->_option['where'] ?? [];
        //清除查询条件
        $this->removeOption(['where', 'field', 'sort', 'limit', 'skip']);
        return $this->getManager()->executeQuery(
            $this->db . '.' . $collection,
            new Query($where, $projection)
        );
    }

    public function setDb($db = '')
    {
        $this->db = $db;
        return $this;
    }

    /**
     * 设置集合名称
     * @param string $collection
     * @return $this
     */
    public function setcollection(string $collection = '')
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * 根据条件获得集合内文档数目
     * @param array $where
     * @return int
     */
    public function count(array $where = [])
    {
        $command = new Command(['count' => $this->collection, 'query' => $where]);
        $result = $this->getManager()->executeCommand($this->db, $command);
        $res = $result->toArray();
        $count = 0;
        if ($res)
        {
            $count = $res[0]->n;
        }
        return $count;
    }

    /**
     * 获得自增id
     * @param $name
     * @param array $param
     * @return mixed
     */
    function getNextId($name, $param = [])
    {
        $Manager = $this->getManager();
        $param += [   //默认ID从1开始,间隔是1
                      'init' => 1,
                      'step' => 1,
        ];
        $update = ['$inc' => ['id' => $param['step']]];   //设置间隔
        $query = ['_id' => $this->collection];
        $command = new Command(
            [
                'findandmodify' => $name,
                'update'        => $update,
                'query'         => $query,
                'new'           => TRUE,
            ]
        );
        $next_id = $Manager->executeCommand($this->db, $command)->toarray();
        if (!empty($next_id[0]->value->id))
        {
            return $next_id[0]->value->id;
        } else
        {
            $BulkWrite = new BulkWrite();
            $BulkWrite->insert(
                [
                    'id'  => $param['init'],     //设置ID起始数值
                    '_id' => $this->collection,
                ]
            );
            $Manager->executeBulkWrite($this->db . '.' . $name, $BulkWrite);
            return $param['init'];
        }
    }

    /**
     * 聚合操作
     * @param string $collection    集合名字
     * @param array $pipeline       管到操作数组  二维数组
     * @return mixed
     */
    public function aggregate(string $collection, array $pipeline)
    {
        $command = new Command(
            [
                'aggregate' => $collection,
                'pipeline'  => $pipeline,
                'cursor'    => ['batchSize' => 0],
            ]
        );
        return $this->getManager()->executeCommand($this->db, $command);
    }

    /**
     * 如果用户客服关联表不存在就创建一条数据
     * @return mixed
     */
    function is_member_customer()
    {
        $collection = 'member_customer';
        $Manager = $this->getManager();
        $param = [   //默认ID从1开始,间隔是1
                     'init' => 1,
                     'step' => 1,
        ];
        $update = ['$inc' => ['id' => $param['step']]];   //设置间隔
        $query = ['name' => 'count'];
        $command = new Command(
            [
                'findandmodify' => $collection,
                'update'        => $update,
                'query'         => $query,
                'new'           => TRUE,
            ]
        );
        $next_id = $Manager->executeCommand($this->db, $command)->toarray();
        if (!empty($next_id[0]->value->id))
        {
            return $next_id[0]->value->id;
        } else
        {
            $BulkWrite = new BulkWrite();
            $BulkWrite->insert(
                [
                    'id'   => $param['init'],     //设置ID起始数值
                    'name' => 'count',
                ]
            );
            $Manager->executeBulkWrite($this->db . '.' . $collection, $BulkWrite);
            return $param['init'];
        }
    }

    /**
     * 如果用户店铺关联表不存在就创建一条数据
     * @return mixed
     */
    function is_member_store()
    {
        $collection = 'member_store';
        $Manager = $this->getManager();
        $param = [   //默认ID从1开始,间隔是1
                     'init' => 1,
                     'step' => 1,
        ];
        $update = ['$inc' => ['id' => $param['step']]];   //设置间隔
        $query = ['name' => 'count'];
        $command = new Command(
            [
                'findandmodify' => $collection,
                'update'        => $update,
                'query'         => $query,
                'new'           => TRUE,
            ]
        );
        $next_id = $Manager->executeCommand($this->db, $command)->toarray();
        if (!empty($next_id[0]->value->id))
        {
            return $next_id[0]->value->id;
        } else
        {
            $BulkWrite = new BulkWrite();
            $BulkWrite->insert(
                [
                    'id'   => $param['init'],     //设置ID起始数值
                    'name' => 'count',
                ]
            );
            $Manager->executeBulkWrite($this->db . '.' . $collection, $BulkWrite);
            return $param['init'];
        }
    }

}