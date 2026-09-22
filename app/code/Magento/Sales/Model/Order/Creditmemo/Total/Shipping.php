    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();

        // amounts without tax
        $orderShippingAmount = $order->getShippingAmount();
        $orderBaseShippingAmount = $order->getBaseShippingAmount();
        $allowedAmount = $orderShippingAmount - $order->getShippingRefunded();
        $baseAllowedAmount = $orderBaseShippingAmount - $order->getBaseShippingRefunded();

        // amounts including tax
        $orderShippingInclTax = $order->getShippingInclTax();
        $orderBaseShippingInclTax = $order->getBaseShippingInclTax();
        $allowedAmountInclTax = $this->getAllowedAmountInclTax($order);
        $baseAllowedAmountInclTax = $this->getBaseAllowedAmountInclTax($order);

        [
            $allowedAmount,
            $baseAllowedAmount,
            $allowedAmountInclTax,
            $baseAllowedAmountInclTax
        ] = $this->applyInvoiceShippingRefundCaps(
            $creditmemo,
            $order,
            $allowedAmount,
            $baseAllowedAmount,
            $allowedAmountInclTax,
            $baseAllowedAmountInclTax
        );

        if ($this->priceCurrency->round($baseAllowedAmount) <= 0.0001 && $creditmemo->hasBaseShippingAmount()) {
            $creditmemo->setBaseShippingAmount(0);
            $creditmemo->setBaseShippingInclTax(0);
        }

        // Check if the desired shipping amount to refund was specified (from invoice or another source).
        if ($creditmemo->hasBaseShippingAmount()) {
            // For the conditional logic, we will either use amounts that always include tax -OR- never include tax.
            // The logic uses the 'base' currency to be consistent with what the user (admin) provided as input.
            $useAmountsWithTax = $this->isSuppliedShippingAmountInclTax($order);

            // Since the user (admin) supplied 'desiredAmount' it already has tax -OR- does not include tax
            $desiredAmount = $this->priceCurrency->round($creditmemo->getBaseShippingAmount());
            $maxAllowedAmount = ($useAmountsWithTax ? $baseAllowedAmountInclTax : $baseAllowedAmount);
            $originalTotalAmount = ($useAmountsWithTax ? $orderBaseShippingInclTax : $orderBaseShippingAmount);

            // Note: ($x < $y + 0.0001) means ($x <= $y) for floats
            if ($desiredAmount < $this->priceCurrency->round($maxAllowedAmount) + 0.0001) {
                // since the admin is returning less than the allowed amount, compute the ratio being returned
                $ratio = 0;
                if ($originalTotalAmount > 0) {
                    $ratio = $desiredAmount / $originalTotalAmount;
                }
                // capture amounts without tax
                // Note: ($x > $y - 0.0001) means ($x >= $y) for floats
                if ($desiredAmount > $maxAllowedAmount - 0.0001) {
                    $shippingAmount = $allowedAmount;
                    $baseShippingAmount = $baseAllowedAmount;
                } else {
                    $shippingAmount = $this->priceCurrency->round($orderShippingAmount * $ratio);
                    $baseShippingAmount = $this->priceCurrency->round($orderBaseShippingAmount * $ratio);
                }
                $shippingInclTax = $this->priceCurrency->round($orderShippingInclTax * $ratio);
                $baseShippingInclTax = $this->priceCurrency->round($orderBaseShippingInclTax * $ratio);
            } else {
                $maxAllowedAmount = $order->getBaseCurrency()->format($maxAllowedAmount, null, false);
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Maximum shipping amount allowed to refund is: %1', $maxAllowedAmount)
                );
            }
        } else {
            $shippingAmount = $allowedAmount;
            $baseShippingAmount = $baseAllowedAmount;
            $shippingInclTax = $this->priceCurrency->round($allowedAmountInclTax);
            $baseShippingInclTax = $this->priceCurrency->round($baseAllowedAmountInclTax);
        }

        $creditmemo->setShippingAmount($shippingAmount);
        $creditmemo->setBaseShippingAmount($baseShippingAmount);
        $creditmemo->setShippingInclTax($shippingInclTax);
        $creditmemo->setBaseShippingInclTax($baseShippingInclTax);

        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $shippingAmount);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseShippingAmount);
        return $this;
    }

    private function applyInvoiceShippingRefundCaps(
        \Magento\Sales\Model\Order\Creditmemo $creditmemo,
        Order $order,
        float $allowedAmount,
        float $baseAllowedAmount,
        float $allowedAmountInclTax,
        float $baseAllowedAmountInclTax
    ): array {
        $invoice = $creditmemo->getInvoice();
        if (!$invoice || !$invoice->getId()) {
            return [$allowedAmount, $baseAllowedAmount, $allowedAmountInclTax, $baseAllowedAmountInclTax];
        }

        $refundedShipping = 0.0;
        $baseRefundedShipping = 0.0;
        $refundedShippingInclTax = 0.0;
        $baseRefundedShippingInclTax = 0.0;
        foreach ($order->getCreditmemosCollection() as $existingCreditmemo) {
            if ((int)$existingCreditmemo->getInvoiceId() !== (int)$invoice->getId()) {
                continue;
            }
            if ($creditmemo->getId() && (int)$existingCreditmemo->getId() === (int)$creditmemo->getId()) {
                continue;
            }
            $refundedShipping += (float)$existingCreditmemo->getShippingAmount();
            $baseRefundedShipping += (float)$existingCreditmemo->getBaseShippingAmount();
            $refundedShippingInclTax += (float)$existingCreditmemo->getShippingInclTax();
            $baseRefundedShippingInclTax += (float)$existingCreditmemo->getBaseShippingInclTax();
        }

        $invoiceShippingCap = max(0.0, (float)$invoice->getShippingAmount() - $refundedShipping);
        $invoiceBaseShippingCap = max(0.0, (float)$invoice->getBaseShippingAmount() - $baseRefundedShipping);
        $invoiceShippingInclTaxCap = max(0.0, (float)$invoice->getShippingInclTax() - $refundedShippingInclTax);
        $invoiceBaseShippingInclTaxCap = max(
            0.0,
            (float)$invoice->getBaseShippingInclTax() - $baseRefundedShippingInclTax
        );

        return [
            min($allowedAmount, $invoiceShippingCap),
            min($baseAllowedAmount, $invoiceBaseShippingCap),
            min($allowedAmountInclTax, $invoiceShippingInclTaxCap),
            min($baseAllowedAmountInclTax, $invoiceBaseShippingInclTaxCap),
        ];
    }

    private function getBaseAllowedAmountInclTax(\Magento\Sales\Model\Order\order $order): float
    {
        if ($this->isShippingIncludeTaxWithTaxAfterDiscount($order)) {
            $result = $order->getBaseShippingInclTax();
            foreach ($order->getCreditmemosCollection() as $creditmemo) {
                $result -= $creditmemo->getBaseShippingInclTax();
            }
        } else {
            $result = ($order->getBaseShippingAmount() - $order->getBaseShippingRefunded()) +
                ($order->getBaseShippingTaxAmount() - $order->getBaseShippingTaxRefunded());
        }

        return max($result, 0);
    }