(function (window, $ , main) {
    'use strict';
    var color = '#3398DB';
    window.chart = {
        //图表参数
        options : {
            //短信统计
            smsCount : function (data) {
                return {
                    color: [color],
                    title: {
                        text: '短信发送量'
                    },
                    legend: {
                        data:['发送量']
                    },
                    xAxis: {
                        data: data.keys
                    },
                    tooltip: {},
                    toolbox: {
                        show: true,
                        orient: 'vertical',
                        left: 'right',
                        top: 'center',
                        feature: {
                            mark: {show: true},
                            magicType: {show: true, type: ['line', 'bar']},
                            restore: {show: true},
                            saveAsImage: {show: true}
                        }
                    },
                    yAxis: {},
                    series: [{
                        name: '发送量',
                        type: 'bar',
                        data: data.values
                    }]
                }
            }
        },
        show : function(id,optionType,data) {
            var elm = document.getElementById(id);
            var et = echarts.init(elm);
            et.setOption(this.options[optionType](data));
        }
    };
})(window,window.jQuery,window.main);