(function(e){e.fn.extend({costEstimatr:function(t){var n={price:e("span#price"),showDaysEstimate:true,days:e("span#days"),totalPrice:e("input#totalPrice"),dollarsPerDay:200};var t=e.extend(n,t);return this.each(function(){var r=t,i=e(this),s=i.find(":input");i[0].reset();e.each(s,function(){var t=e(this),r=t.attr("data-price"),i=/^[+-]?\d+(\.\d+)?([eE][+-]?\d+)?$/;if(i.test(r)){t.bind("change",function(){var t=0;e("input:checked").each(function(){var n=e(this).attr("data-price");t+=parseFloat(n)});n.price.html(t.toFixed(2));n.totalPrice.val(t.toFixed(2));if(n.showDaysEstimate===true){n.days.html(Math.round(t/n.dollarsPerDay))}})}})})}})})(jQuery)