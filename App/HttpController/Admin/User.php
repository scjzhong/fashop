<?php
/**
 * 用户
 *
 *
 *
 *
 * @copyright  Copyright (c) 2019 MoJiKeJi Inc. (http://www.fashop.cn)
 * @license    http://www.fashop.cn
 * @link       http://www.fashop.cn
 * @since      File available since Release v1.1
 */

namespace App\HttpController\Admin;

use App\Utils\Code;

/**
 * 会员
 * Class User
 * @package App\HttpController\Admin
 */
class User extends Admin
{
	/**
	 * 客户管理
	 * @method GET
	 * @param string keywords_type 关键词类型，如：name nickname phone
	 * @param string keywords 关键词
	 * @param int buy_times 购买次数
	 * @param int cost_total 累计消费
	 * @param int cost_average 客单价(平均消费)
	 * @param string create_time 注册时间区间最小值
	 * @param string last_cost_time 最后消费时间区间最小值
	 */
	public function list()
	{
		$param = !empty( $this->post ) ? $this->post : $this->get;

		$userLogic = new \App\Biz\UserSearch( (array)$param );
		$userLogic->page( $this->getPageLimit() );
		$this->send( Code::success, [
			'list'         => $userLogic->list(),
			'total_number' => $userLogic->count(),
		] );
	}

	/**
	 * 新增客户
	 * @method POST
	 * @param string name 姓名
	 * @param string password 密码
	 * @param string phone 手机号
	 * @param int sex 性别 1男0女
	 * @param int  birthday 生日
	 */
	public function add()
	{
		if( $this->validator( $this->post, 'Admin/User.add' ) !== true ){
			$this->send( Code::error, [], $this->getValidator()->getError() );
		} else{
			$data['name']        = $this->post['name'];
			$data['username']    = $this->post['phone'];
			$data['password']    = \App\Biz\User::encryptPassword( $this->post['password'] );
			$data['phone']       = $this->post['phone'];
			$data['create_time'] = time();
			$result              = \App\Model\User::init()->addUser( $data );
			if( !$result ){
				$this->send( Code::error );
			} else{
				$this->send( Code::success );
			}
		}
	}

	/**
	 * 客户详情-客户信息
	 * @method GET
	 * @param int id 用户id
	 */
	public function info()
	{
		if( $this->validator( $this->get, 'Admin/User.info' ) !== true ){
			$this->send( Code::error, [], $this->getValidator()->getError() );
		} else{
			$condition['user.id'] = $this->get['id'];
			$field                = 'user.id,username,phone,email,state,create_time,user_profile.name,nickname,avatar,sex,birthday,qq';
			$user                 = \App\Model\UserProfile::init()->join( 'user', 'user.id = user_profile.user_id', 'LEFT' )->getUserProfileInfo( $condition, $field );
			$this->send( Code::success, ['info' => $user] );
		}
	}

	/**
	 * 客户详情-交易概况-统计
	 * @method GET
	 * @param int id 用户id
	 */
	public function statistics()
	{
		if( $this->validator( $this->get, 'Admin/User.info' ) !== true ){
			$this->send( Code::error, [], $this->getValidator()->getError() );
		} else{
			$user_id           = $this->get['id'];
			$table_prefix      = \EasySwoole\EasySwoole\Config::getInstance()->getConf( 'MYSQL.prefix' );
			$table_user        = $table_prefix.'user';
			$table_order       = $table_prefix.'order';
			$table_order_goods = $table_prefix.'order_goods';
			$table_user_open   = $table_prefix.'user_open';

			$common_string = "SELECT GROUP_CONCAT(distinct user_id SEPARATOR '_') FROM ".$table_user_open." WHERE user_id=".$table_user.".id";


			//退款次数
			$refund_times_string = "(SELECT COUNT(*) FROM $table_order_goods WHERE lock_state=1 AND user_id IN ($common_string))";

			//退款金额
			$refund_total_string = "(SELECT SUM(goods_pay_price) FROM $table_order_goods WHERE lock_state=1 AND user_id IN ($common_string))";

			//购买次数
			$buy_times_string = "(SELECT COUNT(*) FROM $table_order WHERE state>=20 AND user_id IN ($common_string))";//计算总订单的所有的已付款的购买次数

			//客单价(平均消费)
			$cost_average_string = "(SELECT TRUNCATE(IFNULL(AVG(goods_pay_price),0),2) FROM $table_order_goods WHERE lock_state=0 AND user_id=$table_user.id AND order_id IN (SELECT id FROM $table_order WHERE user_id IN ($common_string) AND state>=20))";//计算子订单的未退款的平均消费

			//累计消费
			$cost_total_string = "(SELECT SUM(goods_pay_price) FROM $table_order_goods WHERE lock_state=0 AND user_id=$table_user.id AND order_id IN (SELECT id FROM $table_order WHERE user_id IN ($common_string) AND state>=20))"; //计算子订单的未退款的累计订单金额


			$field = "id,$refund_times_string AS refund_times,$refund_total_string AS refund_total,$buy_times_string AS buy_times,$cost_average_string AS cost_average,$cost_total_string AS cost_total";

			$user                 = \App\Model\User::init()->getUserInfo( ['id' => $user_id], $field );
			$user['refund_times'] = intval( $user['refund_times'] ) > 0 ? intval( $user['refund_times'] ) : 0;
			$user['refund_total'] = $user['refund_total'] > 0 ? $user['refund_total'] : 0;
			$user['buy_times']    = intval( $user['buy_times'] ) > 0 ? intval( $user['buy_times'] ) : 0;
			$user['cost_average'] = $user['cost_average'] > 0 ? $user['cost_average'] : 0;
			$user['cost_total']   = $user['cost_total'] > 0 ? $user['cost_total'] : 0;
			$this->send( Code::success, ['info' => $user] );
		}


	}

	/**
	 * 客户详情-交易概况-用户订单
	 * @method GET
	 * @param int id 用户id
	 */
	public function order()
	{
		if( $this->validator( $this->get, 'Admin/User.info' ) !== true ){
			$this->send( Code::error, [], $this->getValidator()->getError() );
		} else{

			$condition['user_id'] = ['in', \App\Model\User::init()->getUserAllIds( $this->get['id'] )];
			$count                = \App\Model\Order::init()->where( $condition )->count();
			$order_list           = \App\Model\Order::init()->getOrderList( $condition, '', "*", "id desc", $this->getPageLimit(), [
				'order_goods',
				'order_extend',
				'user',
			] );
			$this->send( Code::success, [
				'total_number' => $count,
				'list'         => $order_list,
			] );
		}
	}

	/**
	 * 客户详情-交易概况-收货信息
	 * @method GET
	 * @param int id 用户id
	 */
	public function address()
	{
		if( $this->validator( $this->get, 'Admin/User.info' ) !== true ){
			$this->send( Code::error, [], $this->getValidator()->getError() );
		} else{
			$condition['user_id'] = ['in', \App\Model\User::init()->getUserAllIds( $this->get['id'] )];
			$count                = \App\Model\Address::init()->where( $condition )->count();
			$list                 = \App\Model\Address::init()->getAddressList( $condition, '*', 'id desc', $this->getPageLimit() );
			$this->send( Code::success, [
				'total_number' => $count,
				'list'         => $list,
			] );
		}
	}

	/**
	 * 修改用户
	 * @method POST
	 * @param int id 用户ID
	 * @param string avatar 用户头像
	 * @param string name 用户姓名
	 * @param string password 用户密码
	 * @param string repassword 用户确认密码
	 */
	public function edit()
	{

		if( $this->validator( $this->post, 'Admin/User.edit' ) !== true ){
			$this->send( Code::error, [], $this->getValidator()->getError() );
		} else{
			$condition       = [];
			$condition['id'] = $this->post['id'];
			if( isset( $this->post['avatar'] ) ){//修改头像
				$updata['avatar'] = $this->post['avatar'];
			} elseif( isset( $this->post['name'] ) ){//修改姓名
				$updata['name'] = $this->post['name'];

			} elseif( isset( $this->post['password'] ) ){//修改密码
				if( !isset( $this->post['repassword'] ) ){
					$this->send( Code::error, [], '密码不可为空' );
				} elseif( $this->post['password'] != $this->post['repassword'] ){
					$this->send( Code::error, [], '两次输入不相同' );
				} else{
					$updata['password'] = $this->post['password'];
				}
			}
			if( !$updata ){
				$this->send( Code::param_error );
			} else{
				$updata['password'] = \App\Biz\User::encryptPassword( $this->post['password'] );
				\App\Model\User::init()->editUser( $condition, $updata );
				$this->send( Code::success );
			}
		}
	}


}
