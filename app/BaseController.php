<?php
/*
 * @description: 
 * @Date: 2022-09-26 17:52:37
 * @LastEditTime: 2022-09-26 20:28:17
 */

declare(strict_types=1);

namespace app;

use app\model\SettingModel;
use app\model\TokenModel;
use app\model\UserModel;

use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * 控制器基础类
 */
class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param App $app 应用对象
     */
    protected $user_temp = false;
    private $SettingConfig = false;
    public $auth = false;

    public function __construct(App $app)
    {

        $this->app = $app;
        $this->request = $this->app->request;
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
        if ($this->Setting('authCode', env('authCode', false), true)) {
            $this->auth = true;
        }
    }

    //系统设置项
    public function Setting($key = false, $def = false, $emptyReplace = false)
    {
        if ($this->SettingConfig === false) {
            $this->SettingConfig = SettingModel::Config();
        }
        if ($key) {
            if (isset($this->SettingConfig[$key])) {
                if ($emptyReplace && empty($this->SettingConfig[$key])) {
                    return $def;
                }
                return $this->SettingConfig[$key];
            }
            return $def;
        }
        return $this->SettingConfig;
    }

    /**
     * @description :用户信息获取
     * @param false $must 是否强制验证，true则强制验证程序退出
     * @return TokenModel|array|bool|mixed|Model|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUser(bool $must = false)
    {
        $id = $this->request->header("Userid",'');
        $token = $this->request->header("Token",'');
        if (!$id) {
            $id = $this->request->cookie('user_id', '');
        }
        if (!$token) {
            $token = $this->request->cookie('token', '');
        }
        if ($id && $token) {
            if ($this->user_temp) return $this->user_temp;
            $user = TokenModel::where("user_id", $id)->where('token', $token)->field("user_id,token,create_time")->find();
            if ($user) {
                if (time() > ($user['create_time'] + 60 * 60 * 24 * 15)) {//如果创建时间大于15天则删除
                    $user->delete();
                } else {
                    if ((time() - $user['create_time']) > (864000)) { //token定时15天清理一次，10-15天内如果使用了则重新计算时间
                        $user->create_time = time();
                        $user->save();
                    }
                    $this->user_temp = $user;
                    return $user;
                }
            }
        }
        if ($must) {
            $this->error("请登录后操作")->send();
            exit();
        }
        return false;
    }

    //admin认证
    public function getAdmin()
    {
        $user = $this->getUser(true);
        $info = UserModel::where('id', $user['user_id'])->where("manager", 1)->find();
        if ($info) {
            return $info;
        }
        $this->error('not permission')->send();
        exit();
    }

    public function success($msg, $data = []): \think\response\Json
    {
        if (is_array($msg)) {
            return json(['msg' => "", "code" => 1, "data" => $msg]);
        }
        return json(['msg' => $msg, "code" => 1, "data" => $data]);
    }

    public function error($msg, $data = []): \think\response\Json
    {
        if (is_array($msg)) {
            return json(['msg' => "", "code" => 0, "data" => $msg]);
        }
        return json(['msg' => $msg, "code" => 0, "data" => $data]);
    }
}
