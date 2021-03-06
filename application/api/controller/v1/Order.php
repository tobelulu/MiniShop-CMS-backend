<?php


namespace app\api\controller\v1;


use think\facade\Hook;
use think\facade\Request;
use app\api\model\Order as OrderModel;
use app\lib\exception\order\OrderException;
use app\api\service\Order as OrderService;
use app\api\service\WxPay as WxPayService;
use app\api\model\DeliverRecord as DeliverRecordModel;
use app\lib\enum\OrderStatusEnum;

class Order
{
    /**
     * 分页查询所有订单记录
     * @auth('查询订单','订单管理')
     * @validate('OrderForm')
     */
    public function getOrders()
    {
        $params = Request::get();
        $orders = OrderModel::getOrdersPaginate($params);
        if ($orders['total_nums'] === 0) {
            throw new OrderException([
                'code' => 404,
                'msg' => '未查询到相关订单',
                'error_code' => '70007'
            ]);
        }
        return $orders;
    }

    /**
     * 订单发货
     * @auth('订单发货','订单管理')
     * @param('id','订单id','require|number')
     * @param('comp','快递公司编码','require|alpha')
     * @param('number','快递单号','require|alphaNum')
     */
    public function deliverGoods($id)
    {
        $params = Request::post();
        $result = (new OrderService($id))->deliverGoods($params['comp'], $params['number']);
        return writeJson(201, $result, '发货成功');
    }

    /**
     * 查询订单支付状态
     * @auth('查询订单','订单管理')
     * @param $orderNo
     */
    public function getOrderPayStatus($orderNo)
    {
        $result = (new WxPayService($orderNo))->getWxOrderStatus();
        return $result;
    }

    /**
     * 订单退款
     * @auth('订单退款','订单管理')
     * @params('order_no','订单号','require')
     * @params('refund_fee','退款金额','require|float|>:0')
     */
    public function refund()
    {
        $params = Request::post();
        $result = (new WxPayService($params['order_no']))->refund($params['refund_fee']);
        Hook::listen('logger', "操作订单{$params['order_no']}退款,退款金额{$params['refund_fee']}");
        return $result;
    }

    /**
     * 查询订单退款详情
     * @auth('查询订单','订单管理')
     * @param $orderNo
     */
    public function refundQuery($orderNo)
    {
        $result = (new WxPayService($orderNo))->refundQuery();
        return $result;
    }

    /**
     * 分页查询订单发货记录
     * @auth('查询发货记录','日志')
     * @validate('DeliverRecordForm')
     */
    public function getOrderDeliverRecord()
    {
        $params = Request::get();
        $result = DeliverRecordModel::getDeliverRecordPaginate($params);
        if ($result['total_nums'] === 0) {
            throw new OrderException([
                'code' => 404,
                'msg' => '未查询到相关发货记录',
                'error_code' => '70010'
            ]);
        }
        return $result;
    }

    /**
     * @auth('关闭订单','订单管理')
     * @param('id','订单id','require|number')
     */
    public function close($id)
    {
        OrderModel::update(['id' => $id, 'status' => OrderStatusEnum::CLOSED]);
        return writeJson(201, '订单关闭成功');
    }

    /**
     * @auth('修改状态为已支付','订单管理')
     * @param('id','订单id','require|number')
     */
    public function changeToPaid($id)
    {
        OrderModel::update(['id' => $id, 'status' => OrderStatusEnum::PAID]);
        return writeJson(201, '修改订单状态为已支付成功');
    }

}
