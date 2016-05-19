<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Model\Common\Setting;
use App\Model\Common\Template;
use App\Model\Order\Invoice;
use App\Model\Order\InvoiceItem;
use App\Model\Order\Payment;
use App\Model\Payment\Currency;
use App\Model\Payment\Promotion;
use App\Model\Payment\Tax;
use App\Model\Payment\TaxOption;
use App\Model\Product\Price;
use App\Model\Product\Product;
//use Symfony\Component\HttpFoundation\Request as Requests;
use App\User;
use Illuminate\Http\Request;
use Input;
use App\Http\Controllers\Common\TemplateController;

class InvoiceController extends Controller {

    public $invoice;
    public $invoiceItem;
    public $user;
    public $template;
    public $setting;
    public $payment;
    public $product;
    public $price;
    public $promotion;
    public $currency;
    public $tax;
    public $tax_option;

    public function __construct() {
        $this->middleware('auth');
//        $this->middleware('admin');

        $invoice = new Invoice();
        $this->invoice = $invoice;

        $invoiceItem = new InvoiceItem();
        $this->invoiceItem = $invoiceItem;

        $user = new User();
        $this->user = $user;

        $template = new Template();
        $this->template = $template;

        $seting = new Setting();
        $this->setting = $seting;

        $payment = new Payment();
        $this->payment = $payment;

        $product = new Product();
        $this->product = $product;

        $price = new Price();
        $this->price = $price;

        $promotion = new Promotion();
        $this->promotion = $promotion;

        $currency = new Currency();
        $this->currency = $currency;

        $tax = new Tax();
        $this->tax = $tax;

        $tax_option = new TaxOption();
        $this->tax_option = $tax_option;
    }

    public function index() {
        try {
            //dd($this->invoice->get());
            return view('themes.default1.invoice.index');
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function GetInvoices() {
        //dd($this->invoice->get());
        //$invoice = \DB::table('invoices');
        return \Datatable::query($this->invoice->select('id', 'user_id', 'number', 'date', 'grand_total', 'status', 'created_at'))
                        ->addColumn('#', function ($model) {
                            return "<input type='checkbox' value=" . $model->id . ' name=select[] id=check>';
                        })
                        ->addColumn('user_id', function ($model) {

                            $first = $this->user->where('id', $model->user_id)->first()->first_name;
                            $last = $this->user->where('id', $model->user_id)->first()->last_name;
                            $id = $this->user->where('id', $model->user_id)->first()->id;

                            return '<a href=' . url('clients/' . $id) . '>' . ucfirst($first) . ' ' . ucfirst($last) . '</a>';
                        })
                        ->showColumns('number', 'created_at', 'grand_total', 'status')
                        ->addColumn('action', function ($model) {
                            $order = \App\Model\Order\Order::where('invoice_id', $model->id)->first();
                            if (!$order) {
                                $action = '<a href=' . url('order/execute?invoiceid=' . $model->id) . " class='btn btn-sm btn-primary'>Execute Order</a>";
                            } else {
                                $action = '';
                            }

                            return '<a href=' . url('invoices/show?invoiceid=' . $model->id) . " class='btn btn-sm btn-primary'>View</a>"
                                    . "   $action";
                        })
                        ->searchColumns('created_at', 'user_id', 'number', 'grand_total', 'status')
                        ->orderColumns('created_at', 'user_id', 'number', 'grand_total', 'status')
                        ->make();
    }

    public function show(Request $request) {
        try {
            $id = $request->input('invoiceid');
            $invoice = $this->invoice->where('id', $id)->first();
            $invoiceItems = $this->invoiceItem->where('invoice_id', $id)->get();
            $user = $this->user->find($invoice->user_id);

            return view('themes.default1.invoice.show', compact('invoiceItems', 'invoice', 'user'));
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    /**
     * not in use case.
     *
     * @param Request $request
     *
     * @return type
     */
    public function generateById(Request $request) {
        try {
            $clientid = $request->input('clientid');
            //dd($clientid);
            if ($clientid) {
                $user = new User();
                $user = $user->where('id', $clientid)->first();
                if (!$user) {
                    return redirect()->back()->with('fails', 'Invalid user');
                }
            } else {
                $user = '';
            }
            $products = $this->product->lists('name', 'id')->toArray();
            $currency = $this->currency->lists('name', 'code')->toArray();

            return view('themes.default1.invoice.generate', compact('user', 'products', 'currency'));
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function invoiceGenerateByForm(Request $request, $user_id = '') {

        if (key_exists('domain', $request->all())) {
            $this->validate($request, [
                'domain' => 'required|url'
            ]);
        }

        $this->validate($request, [
            'product' => 'required',
        ]);
        try {
            if ($user_id == '') {
                $user_id = \Input::get('user');
            }

            $productid = Input::get('product');
            $code = Input::get('code');
            $total = Input::get('price');
            if ($request->has('domain')) {
                $domain = $request->input('domain');
                $this->setDomain($productid, $domain);
            }
            $currency = $this->user->find($user_id)->currency;
            if (!$currency) {
                $currency = 'USD';
            }
            if ($currency == 1) {
                $currency = 'USD';
            } else {
                $currency = 'INR';
            }
            $number = rand(11111111, 99999999);
            $date = \Carbon\Carbon::now();
            $product = $this->product->findOrFail($productid);
            $price = $product->price()->where('currency', $currency)->first();
            //dd($price);
            $cost = $price->sales_price;
            if (!$cost) {
                $cost = $price->price;
            }
            if ($cost != $total) {
                $grand_total = $total;
            }
            //dd($cost);
            if ($code) {
                $grand_total = $this->checkCode($code, $productid);
                //dd($grand_total);
            } else {
                if (!$total) {
                    $grand_total = $cost;
                } else {
                    $grand_total = $total;
                }
            }
            $grand_total = $grand_total;
            //dd($grand_total);
            $tax = $this->checkTax($product->id);
            //dd($tax);
            $tax_name = '';
            $tax_rate = '';
            if (!empty($tax)) {
                foreach ($tax as $key => $value) {
                    //dd($value);
                    $tax_name .= $value['name'] . ',';
                    $tax_rate .= $value['rate'] . ',';
                }
            }
            //dd('dsjcgv');
            $grand_total = $this->calculateTotal($tax_rate, $grand_total);

            //dd($grand_total);
            $grand_total = \App\Http\Controllers\Front\CartController::rounding($grand_total);

            $invoice = $this->invoice->create(['user_id' => $user_id, 'number' => $number, 'date' => $date, 'grand_total' => $grand_total, 'currency' => $currency, 'status' => 'pending']);
            if ($grand_total > 0) {
                $this->doPayment('online payment', $invoice->id, $grand_total, '', $user_id);
            }
            $items = $this->createInvoiceItemsByAdmin($invoice->id, $productid, $code, $total, $currency);
            if ($items) {

                $this->sendmailClientAgent($user_id, $items->invoice_id);
                $result = ['success' => \Lang::get('message.invoice-generated-successfully')];
            } else {
                $result = ['fails' => \Lang::get('message.can-not-generate-invoice')];
            }
        } catch (\Exception $ex) {
            $result = ['fails' => $ex->getMessage()];
        }

        return response()->json(compact('result'));
    }

    public function sendmailClientAgent($userid, $invoiceid) {
        try {
            $agent = \Input::get('agent');
            $client = \Input::get('client');
            if ($agent == 1) {
                $id = \Auth::user()->id;
                $this->sendMail($id, $invoiceid);
            }
            if ($client == 1) {
                $this->sendMail($userid, $invoiceid);
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * Generate invoice.
     *
     * @throws \Exception
     */
    public function GenerateInvoice() {
        try {
            $tax_rule = new \App\Model\Payment\TaxOption();
            $rule = $tax_rule->findOrFail(1);
            $rounding = $rule->rounding;

            $user_id = \Auth::user()->id;
            $grand_total = \Cart::getSubTotal();

            $number = rand(11111111, 99999999);
            $date = \Carbon\Carbon::now();

            if ($rounding == 1) {
                $grand_total = round($grand_total);
            }

            $invoice = $this->invoice->create(['user_id' => $user_id, 'number' => $number, 'date' => $date, 'grand_total' => $grand_total, 'status' => 'pending']);

            foreach (\Cart::getContent() as $cart) {
                $this->createInvoiceItems($invoice->id, $cart);
            }

            return $invoice;
        } catch (\Exception $ex) {
            dd($ex);
            throw new \Exception('Can not Generate Invoice');
        }
    }

    public function createInvoiceItems($invoiceid, $cart) {
        try {
            $product_name = $cart->name;
            $regular_price = $cart->price;
            $quantity = $cart->quantity;
            $domain = $this->domain($cart->id);
            //dd($quantity);
            $subtotal = \App\Http\Controllers\Front\CartController::rounding($cart->getPriceSumWithConditions());

            $tax_name = '';
            $tax_percentage = '';

            foreach ($cart->attributes['tax'] as $tax) {
                //dd($tax['name']);
                $tax_name .= $tax['name'] . ',';
                $tax_percentage .= $tax['rate'] . ',';
            }

//            dd($tax_name);

            $invoiceItem = $this->invoiceItem->create([
                'invoice_id' => $invoiceid,
                'product_name' => $product_name,
                'regular_price' => $regular_price,
                'quantity' => $quantity,
                'tax_name' => $tax_name,
                'tax_percentage' => $tax_percentage,
                'subtotal' => $subtotal,
                'domain' => $domain,
            ]);

            return $invoiceItem;
        } catch (\Exception $ex) {
            dd($ex);
            throw new \Exception('Can not create Invoice Items');
        }
    }

    public function doPayment($payment_method, $invoiceid, $amount, $parent_id = '', $userid = '', $payment_status = 'pending') {
        try {
            if ($amount > 0) {
                if ($userid == '') {
                    $userid = \Auth::user()->id;
                }
                if ($amount == 0) {
                    $payment_status = 'success';
                }
                $this->payment->create([
                    'parent_id' => $parent_id,
                    'invoice_id' => $invoiceid,
                    'user_id' => $userid,
                    'amount' => $amount,
                    'payment_method' => $payment_method,
                    'payment_status' => $payment_status,
                ]);
                $this->updateInvoice($invoiceid);
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    public function createInvoiceItemsByAdmin($invoiceid, $productid, $code = '', $price = '', $currency = 'USD') {
        try {
            $discount = '';
            $mode = '';
            $product = $this->product->findOrFail($productid);
            $price_model = $this->price->where('product_id', $product->id)->where('currency', $currency)->first();
            if ($price == '') {
                $price = $price_model->sales_price;
                if (!$price) {
                    $price = $price_model->price;
                }
            }
            $subtotal = $price;
            //dd($subtotal);
            if ($code) {
                $subtotal = $this->checkCode($code, $productid);
                $mode = 'coupon';
                $discount = $price - $subtotal;
            }
            $tax = $this->checkTax($product->id);
            //dd($tax);
            $tax_name = '';
            $tax_rate = '';
            if (!empty($tax)) {
                foreach ($tax as $key => $value) {
                    //dd($value);
                    $tax_name .= $value['name'] . ',';
                    $tax_rate .= $value['rate'] . ',';
                }
            }
            $subtotal = $this->calculateTotal($tax_rate, $subtotal);
            $domain = $this->domain($productid);
            $items = $this->invoiceItem->create([
                'invoice_id' => $invoiceid,
                'product_name' => $product->name,
                'regular_price' => $price,
                'quantity' => 1,
                'discount' => $discount,
                'discount_mode' => $mode,
                'subtotal' => \App\Http\Controllers\Front\CartController::rounding($subtotal),
                'tax_name' => $tax_name,
                'tax_percentage' => $tax_rate,
                'domain' => $domain,
            ]);

            return $items;
        } catch (\Exception $ex) {
            dd($ex);

            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function checkCode($code, $productid) {
        try {
            if ($code != '') {
                $promo = $this->promotion->where('code', $code)->first();
                //check promotion code is valid
                if (!$promo) {
                    throw new \Exception(\Lang::get('message.no-such-code'));
                }
                $relation = $promo->relation()->get();
                //check the relation between code and product
                if (count($relation) == 0) {
                    throw new \Exception(\Lang::get('message.no-product-related-to-this-code'));
                }
                //check the usess
                $uses = $this->checkNumberOfUses($code);
                //dd($uses);
                if ($uses != 'success') {
                    throw new \Exception(\Lang::get('message.usage-of-code-completed'));
                }
                //check for the expiry date
                $expiry = $this->checkExpiry($code);
                //dd($expiry);
                if ($expiry != 'success') {
                    throw new \Exception(\Lang::get('message.usage-of-code-expired'));
                }
                $value = $this->findCostAfterDiscount($promo->id, $productid);

                return $value;
            } else {
                $product = $this->product->find($productid);
                $price = $product->price()->sales_price;
                if (!$price) {
                    $price = $product->price()->price;
                }

                return $price;
            }
        } catch (\Exception $ex) {
            dd($ex);
            throw new \Exception(\Lang::get('message.check-code-error'));
        }
    }

    public function findCostAfterDiscount($promoid, $productid) {
        try {
            $promotion = $this->promotion->findOrFail($promoid);
            $product = $this->product->findOrFail($productid);
            $promotion_type = $promotion->type;
            $promotion_value = $promotion->value;
            $product_price = $product->price()->first()->sales_price;
            if (!$product_price) {
                $product_price = $product->price()->first()->price;
            }
            $updated_price = $this->findCost($promotion_type, $promotion_value, $product_price, $productid);

            return $updated_price;
        } catch (\Exception $ex) {
            throw new \Exception(\Lang::get('message.find-discount-error'));
        }
    }

    public function findCost($type, $value, $price, $productid) {
        try {
            switch ($type) {

                case 1:
                    $percentage = $price * ($value / 100);

                    return $price - $percentage;
                case 2:
                    return $price - $value;
                case 3:
                    return $value;
                case 4:
                    return 0;
            }
        } catch (\Exception $ex) {
            throw new \Exception(\Lang::get('message.find-cost-error'));
        }
    }

    public function checkNumberOfUses($code) {
        try {
            $promotion = $this->promotion->where('code', $code)->first();
            $uses = $promotion->uses;
            if ($uses == 0) {
                return 'success';
            }
            $used_number = $this->invoice->where('coupon_code', $code)->count();
            if ($uses >= $used_number) {
                return 'success';
            } else {
                return 'fails';
            }
        } catch (\Exception $ex) {
            throw new \Exception(\Lang::get('message.find-cost-error'));
        }
    }

    public function checkExpiry($code = '') {
        try {
            if ($code != '') {
                $promotion = $this->promotion->where('code', $code)->first();
                $start = $promotion->start;
                $end = $promotion->expiry;
                //dd($end);
                $now = \Carbon\Carbon::now();
                //both not set, always true
                if (($start == null || $start == '0000-00-00 00:00:00') && ($end == null || $end == '0000-00-00 00:00:00')) {
                    return 'success';
                }
                //only starting date set, check the date is less or equel to today
                if (($start != null || $start != '0000-00-00 00:00:00') && ($end == null || $end == '0000-00-00 00:00:00')) {
                    if ($start <= $now) {
                        return 'success';
                    }
                }
                //only ending date set, check the date is greater or equel to today
                if (($end != null || $end != '0000-00-00 00:00:00') && ($start == null || $start == '0000-00-00 00:00:00')) {
                    if ($end >= $now) {
                        return 'success';
                    }
                }
                //both set
                if (($end != null || $start != '0000-00-00 00:00:00') && ($start != null || $start != '0000-00-00 00:00:00')) {
                    if ($end >= $now && $start <= $now) {
                        return 'success';
                    }
                }
            } else {
                
            }
        } catch (\Exception $ex) {
            dd($ex);
            throw new \Exception(\Lang::get('message.check-expiry'));
        }
    }

    public function checkTax($productid) {
        try {
            //dd($productid);
            $taxs[0] = ['name' => 'null', 'rate' => 0];
            $product = $this->product->findOrFail($productid);
            if ($this->tax_option->findOrFail(1)->inclusive == 0) {
                if ($product->tax()->first()) {
                    $tax_class_id = $product->tax()->first()->tax_class_id;
                } else {
                    return $taxs;
                }

                if ($this->tax_option->findOrFail(1)->tax_enable == 1) {
                    $cart_controller = new \App\Http\Controllers\Front\CartController();
                    $taxes = $cart_controller->getTaxByPriority($tax_class_id);

                    foreach ($taxes as $key => $tax) {
                        //dd($tax);
                        if ($tax->compound == 1) {
                            $taxs[$key] = ['name' => $tax->name, 'rate' => $tax->rate];
                        } else {
                            $rate = '';
                            $rate += $tax->rate;
                            $taxs[$key] = ['name' => $tax->name, 'rate' => $rate];
                        }
                    }
                }
            }
            //dd($taxs);
            return $taxs;
        } catch (\Exception $ex) {
            dd($ex);
            throw new \Exception(\Lang::get('message.check-tax-error'));
        }
    }

    public function pdf(Request $request) {
        try {
            $id = $request->input('invoiceid');
            if (!$id) {
                return redirect()->back()->with('fails', \Lang::get('message.no-invoice-id'));
            }
            $invoice = $this->invoice->where('id', $id)->first();
            if (!$invoice) {
                return redirect()->back()->with('fails', \Lang::get('message.invalid-invoice-id'));
            }
            $invoiceItems = $this->invoiceItem->where('invoice_id', $id)->get();
            $user = $this->user->find($invoice->user_id);
            //return view('themes.default1.invoice.pdfinvoice', compact('invoiceItems', 'invoice', 'user'));
            $pdf = \PDF::loadView('themes.default1.invoice.pdfinvoice', compact('invoiceItems', 'invoice', 'user'));

            return $pdf->download($user->first_name . '-invoice.pdf');
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function calculateTotal($rate, $total) {
        try {
            //dd($total);
            $rates = explode(',', $rate);
            //dd($rates);
//            $total = '';
            $rule = new TaxOption();
            $rule = $rule->findOrFail(1);
            if ($rule->tax_enable == 1 && $rule->inclusive == 0) {
                foreach ($rates as $rate) {
                    $total += $total * ($rate / 100);
                }
            }
            //dd($total);
            return $total;
        } catch (\Exception $ex) {
            dd($ex);
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function destroy(Request $request) {
        try {
            $ids = $request->input('select');
            if (!empty($ids)) {
                foreach ($ids as $id) {
                    $invoice = $this->invoice->where('id', $id)->first();
                    if ($invoice) {
                        $invoice->delete();
                    } else {
                        echo "<div class='alert alert-danger alert-dismissable'>
                    <i class='fa fa-ban'></i>
                    <b>" . \Lang::get('message.alert') . '!</b> ' . \Lang::get('message.failed') . '
                    <button type=button class=close data-dismiss=alert aria-hidden=true>&times;</button>
                        ' . \Lang::get('message.no-record') . '
                </div>';
                        //echo \Lang::get('message.no-record') . '  [id=>' . $id . ']';
                    }
                }
                echo "<div class='alert alert-success alert-dismissable'>
                    <i class='fa fa-ban'></i>
                    <b>" . \Lang::get('message.alert') . '!</b> ' . \Lang::get('message.success') . '
                    <button type=button class=close data-dismiss=alert aria-hidden=true>&times;</button>
                        ' . \Lang::get('message.deleted-successfully') . '
                </div>';
            } else {
                echo "<div class='alert alert-danger alert-dismissable'>
                    <i class='fa fa-ban'></i>
                    <b>" . \Lang::get('message.alert') . '!</b> ' . \Lang::get('message.failed') . '
                    <button type=button class=close data-dismiss=alert aria-hidden=true>&times;</button>
                        ' . \Lang::get('message.select-a-row') . '
                </div>';
                //echo \Lang::get('message.select-a-row');
            }
        } catch (\Exception $e) {
            echo "<div class='alert alert-danger alert-dismissable'>
                    <i class='fa fa-ban'></i>
                    <b>" . \Lang::get('message.alert') . '!</b> ' . \Lang::get('message.failed') . '
                    <button type=button class=close data-dismiss=alert aria-hidden=true>&times;</button>
                        ' . $e->getMessage() . '
                </div>';
        }
    }

    public function setDomain($productid, $domain) {
        try {
            if (\Session::has('domain' . $productid)) {
                \Session::forget('domain' . $productid);
            }
            \Session::put('domain' . $productid, $domain);
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    public function domain($id) {
        try {
            if (\Session::has('domain' . $id)) {
                $domain = \Session::get('domain' . $id);
            } else {
                $domain = '';
            }

            return $domain;
        } catch (\Exception $ex) {
            
        }
    }

    public function updateInvoice($invoiceid) {
        try {
            $invoice = $this->invoice->findOrFail($invoiceid);
            $payment = $this->payment->where('invoice_id', $invoiceid)->where('payment_status', 'success')->lists('amount')->toArray();
            $total = array_sum($payment);
            if ($total < $invoice->grand_total) {
                $invoice->status = 'pending';
            }
            if ($total >= $invoice->grand_total) {
                $invoice->status = 'success';
            }
            if ($total > $invoice->grand_total) {
                $user = $invoice->user()->first();
                $balance = $total - $invoice->grand_total;
                $user->debit = $balance;
                $user->save();
            }

            $invoice->save();
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    public function updateInvoicePayment($invoiceid, $payment_method, $payment_status = 'pending', $invoice_status = 'pending', $domain = '') {
        try {
            $invoice = $this->invoice->find($invoiceid);
            if ($invoice) {
                $invoice->status = $invoice_status;
                $invoice->save();
                $item = $invoice->invoiceItem()->first();
                if ($item) {
                    $item->domain = $domain;
                    $item->save();
                }
            }
            $payment = $this->payment->where('invoice_id', $invoiceid)->first();
            if ($payment) {
                $payment->payment_status = $payment_status;
                $payment->payment_method = $payment_method;
                $payment->save();
                return $payment;
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    public function payment(Request $request) {
        try {
            if ($request->has('invoiceid')) {
                $invoice_id = $request->input('invoiceid');
                $invoice = $this->invoice->find($invoice_id);
                //dd($invoice);
                $invoice_status = '';
                $payment_status = '';
                $payment_method = '';
                $domain = '';
                if ($invoice) {
                    $invoice_status = $invoice->status;
                    $items = $invoice->invoiceItem()->first();
                    if ($items) {
                        $domain = $items->domain;
                    }
                }
                $payment = $this->payment->where('invoice_id', $invoice_id)->first();
                if ($payment) {
                    $payment_status = $payment->payment_status;
                    $payment_method = $payment->payment_method;
                }

                return view('themes.default1.invoice.payment', compact('invoice_status', 'payment_status', 'payment_method', 'invoice_id', 'domain'));
            }
            return redirect()->back();
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function postPayment($invoiceid, Request $request) {
        try {
            $payment_method = $request->input('payment_method');
            $payment_status = $request->input('payment_status');
            $invoice_status = $request->input('invoice_status');
            $domain = $request->input('domain');
            $payment = $this->updateInvoicePayment($invoiceid, $payment_method, $payment_status, $invoice_status, $domain);
            if ($payment) {
                return redirect()->back()->with('success', 'Payment Accepted Successfully');
            }
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function sendMail($userid, $invoiceid, $templatetype = 7) {
        try {
            $settings = new Setting();
            $settings = $settings->findOrFail(1);
            $user = $this->user->find($userid);
            $name = '';
            $to = '';
            $product = '';
            if ($user) {
                $name = $user->first_name . ' ' . $user->last_name;
                $to = $user->email;
            }
            $invoice = $this->invoice->find($invoiceid);
            $invoice_number = $invoice->number;
            $item = $invoice->invoiceItem()->first();
            if ($item) {
                $product = $item->product_name;
            }

            $url = url("download/$userid/$invoice_number");
            $from = $settings->email;

            $data = $this->template->where('type', $templatetype)->first()->data;
            $subject = $this->template->where('type', $templatetype)->first()->name;
            $replace = ['url' => $url, 'name' => $name, 'product' => $product];

            //send mail
            $template_controller = new TemplateController();
            $template_controller->Mailing($from, $to, $data, $subject, $replace);
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

}