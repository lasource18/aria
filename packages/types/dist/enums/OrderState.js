export var OrderState;
(function (OrderState) {
    OrderState["Created"] = "created";
    OrderState["AwaitingPayment"] = "awaiting_payment";
    OrderState["Paid"] = "paid";
    OrderState["Canceled"] = "canceled";
    OrderState["Refunded"] = "refunded";
    OrderState["Expired"] = "expired";
})(OrderState || (OrderState = {}));
