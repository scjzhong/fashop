<?php
/**
 *
 * Copyright  FaShop
 * License    http://www.fashop.cn
 * link       http://www.fashop.cn
 * Created by FaShop.
 * User: hanwenbo
 * Date: 2018/1/29
 * Time: 下午11:19
 *
 */

namespace App\Cron;


class Order
{

	/**
	 * 待付款订单自动关闭
	 */
	static function autoCloseUnpay() : void
	{
		$config                   = \App\Model\Shop::init()->getShopInfo( ['id' => 1] );
		$condition['state']       = \App\Biz\Order::state_new;
		$condition['create_time'] = ['<', time() - $config['order_auto_close_expires']];
		$order_list               = \App\Model\Order::init()->getOrderList( $condition, '', 'id,user_id,create_time,state', 'id desc', [1,10000] );
		if( !empty( $order_list ) ){
			$now_time = time();
			$tradeLogic  = new \App\Biz\Trade();
			$expireDay   = $config['order_auto_close_expires'] / 60 / 60 / 24;
			foreach( $order_list as $order ){
				$tradeLogic->editOrderCancel( $order['id'], [
					'role'        => 'system',
					'create_time' => $now_time,
					'order_id'    => $order['id'],
					'msg'         => "自动关闭订单：".\App\Biz\Order::state_cancel."，已超过{$expireDay}天",
				] );
			}
		}
	}
	// 自动收货，完成订单
	static function autoReceive() : void
	{
		$config                   = \App\Model\Shop::init()->getShopInfo( ['id' => 1] );
		$condition['state']       = \App\Biz\Order::state_send;
		$condition['lock_state'] =  0;
		$condition['delay_time'] = ['<', time() - $config['order_auto_confirm_expires']];
		$order_list               = \App\Model\Order::init()->getOrderList( $condition, '', 'id,user_id,create_time,state', 'id desc', [1,10000] );
		if( !empty( $order_list ) ){
			$now_time = time();
			$tradeLogic  = new \App\Biz\Trade();
			$expireDay   = $config['order_auto_confirm_expires'] / 60 / 60 / 24;
			foreach( $order_list as $order ){
				$tradeLogic->editOrderFinish( $order['id'], [
					'role'        => 'system',
					'create_time' => $now_time,
					'order_id'    => $order['id'],
					'msg'         => "自动收货订单：".\App\Biz\Order::state_send."，已超过{$expireDay}天",
				] );
			}
		}
	}


}