webpackJsonp([126],{Of3q:function(n,t,e){var i=e("eQ6T");"string"==typeof i&&(i=[[n.i,i,""]]),i.locals&&(n.exports=i.locals);e("rjj0")("979e38ea",i,!0,{})},XTOE:function(n,t,e){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var i=e("dyt9"),a=e("c2Ch"),o={name:"Spokesman",components:{HeadTitle:i.a},data:function(){return{isFinish:!1,ruleList:[],isShow:!1,auditStatus:null,disInfo:null,distribution_id:""}},mounted:function(){this.getData(),this.commonJs.changeColor()},methods:{getData:function(){var n=this;Object(a._149)().then(function(t){n.isFinish=!0,n.ruleList=t.data,n.disInfo=JSON.parse(localStorage.getItem("distributionInfo")),n.auditStatus=null==n.disInfo?null:n.disInfo.audit_status})},apply:function(){var n=this;null==this.disInfo?(this.auditStatus=0,Object(a._29)().then(function(t){console.log(t),n.isShow=!0})):"1"==this.auditStatus&&(this.isShow=!0),this.$nextTick(function(){n.commonJs.changeColor()})},onApply:function(n){console.log(n),0!=n.audit_status?this.$router.push({name:"Application"}):this.$router.push({name:"ApplyWait"})}}},l={render:function(){var n=this,t=n.$createElement,e=n._self._c||t;return n.isFinish?e("div",{staticClass:"levelCon"},[e("head-title",{attrs:{title:"成为代言人"}}),n._v(" "),0!=n.ruleList.length?e("div",n._l(n.ruleList,function(t){return e("div",{staticClass:"spokeCon"},[e("div",{staticClass:"title"},[e("div",{staticClass:"bgActive"}),n._v("\n          "+n._s(t.title)+"\n        ")]),n._v(" "),n._l(t.rule,function(t){return e("div",["apply"==t.keyword?e("div",{staticClass:"det"},[n._m(0,!0),n._v(" "),e("div",{staticClass:"button",style:"background:linear-gradient(to right,rgba("+n.commonJs.getColor().Color.ColorRg+",0.4),"+n.commonJs.getColor().Color.colour+");box-shadow: rgba("+n.commonJs.getColor().Color.ColorRg+", 0.7) 0px 0px 20px;"},[e("a",{on:{click:function(e){n.onApply(t)}}},[n._v("申请代言")])])]):"special_area"==t.keyword?e("div",{staticClass:"det"},[n._m(1,!0),n._v(" "),e("div",{staticClass:"button",style:"background:linear-gradient(to right,rgba("+n.commonJs.getColor().Color.ColorRg+",0.4),"+n.commonJs.getColor().Color.colour+");box-shadow: rgba("+n.commonJs.getColor().Color.ColorRg+", 0.7) 0px 0px 20px;"},[e("router-link",{attrs:{to:{name:"EndorsementZone",query:{distribution_id:n.$route.query.distribution_id}}}},[n._v("代言专区")])],1)]):"full"==t.keyword?e("div",{staticClass:"det"},[e("div",{staticClass:"text"},[e("div",{staticClass:"tit"},[n._v("商城购买商品")]),n._v(" "),e("div",{staticClass:"article"},[n._v("在商城任意下单"),e("span",{staticClass:"colorActive"},[n._v("满"+n._s(t.condition)+"元")]),n._v("即可成为代言人")])]),n._v(" "),e("div",{staticClass:"button",style:"background:linear-gradient(to right,rgba("+n.commonJs.getColor().Color.ColorRg+",0.4),"+n.commonJs.getColor().Color.colour+");box-shadow: rgba("+n.commonJs.getColor().Color.ColorRg+", 0.7) 0px 0px 20px;"},[e("router-link",{attrs:{to:{name:"Represent",query:{distribution_id:n.$route.query.distribution_id}}}},[n._v("去逛逛")])],1)]):n._e()])})],2)}),0):n._e(),n._v(" "),0==n.ruleList.length?e("div",[n.isShow?n._e():e("div",[e("div",{staticClass:"newTipsCon"},[e("img",{staticClass:"pic",attrs:{src:"/static/image/fx-sqdy-icon.png",alt:""}}),n._v(" "),e("div",{staticClass:"btn",style:"background:"+n.commonJs.getColor().Color.colour,on:{click:function(t){n.apply()}}},[n._v("申请成为代言人")])])]),n._v(" "),n.isShow?e("div",[e("div",{staticClass:"newTipsCon"},[e("img",{staticClass:"pic",attrs:{src:"/static/image/fx-sqdy-cg.png",alt:""}}),n._v(" "),e("div",{staticClass:"btn bgActive",on:{click:function(t){n.$router.push({name:"Represent"})}}},[n._v("去代言")])])]):n._e()]):n._e()],1):n._e()},staticRenderFns:[function(){var n=this.$createElement,t=this._self._c||n;return t("div",{staticClass:"text"},[t("div",{staticClass:"tit"},[this._v("申请代言")]),this._v(" "),t("div",{staticClass:"article"},[this._v("请点击"),t("span",{staticClass:"colorActive"},[this._v("“申请代言”")]),this._v("按钮，审核通过后即可代言")])])},function(){var n=this.$createElement,t=this._self._c||n;return t("div",{staticClass:"text"},[t("div",{staticClass:"tit"},[this._v("购买指定商品")]),this._v(" "),t("div",{staticClass:"article"},[this._v("前往"),t("span",{staticClass:"colorActive"},[this._v("代言专区")]),this._v("购买指定商品,下单后即可成为代言人,不可退货,不可取消")])])}]};var s=e("VU/8")(o,l,!1,function(n){e("Of3q")},"data-v-35618894",null);t.default=s.exports},eQ6T:function(n,t,e){(n.exports=e("FZ+f")(!1)).push([n.i,"\n.recordMain[data-v-35618894]{\n  width: 94%;\n  margin: 0 auto;\n  padding: 10px 0 0;\n}\n.recordMain .time[data-v-35618894]{\n  background: #e3e1e1;\n  border-radius: 20px;\n  font-size: 12px;\n  line-height: 26px;\n  color: #fff;\n  text-align: center;\n  width: 124px;\n  margin: 0 auto;\n}\n.recordMain .con[data-v-35618894]{\n  background: #fff;\n  border-radius: 4px;\n  padding: 10px;\n  margin-top: 10px;\n}\n.recordMain .title[data-v-35618894]{\n  font-size: 18px;\n  line-height: 30px;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  overflow: hidden;\n  color: #202224;\n}\n.recordMain .textCon[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n.recordMain .textCon img[data-v-35618894]{\n  display: block;\n  width: 46px;\n  height: 46px;\n  margin-right: 10px;\n}\n.recordMain .textCon .text[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  display: -webkit-box;\n  -webkit-line-clamp: 2;\n  -webkit-box-orient: vertical;\n  font-size: 14px;\n  line-height: 20px;\n  color: #202224;\n}\n.contentRepresent[data-v-35618894] {\n  position: absolute;\n  width: 100%;\n  height: calc(100% - 48px);\n  top: 48px;\n  left: 0;\n  background: #f6f6f6;\n}\n.white[data-v-35618894]:after {\n  background: #fff;\n  position: absolute;\n  width: 100%;\n  height: 100%;\n  left: 0;\n  top: 0;\n  z-index: 2;\n  content: '';\n}\n.withdrawCon[data-v-35618894]{\n  position: relative;\n  padding: 30px 0 100px;\n  color: #fff;\n}\n.withdrawCon .title[data-v-35618894]{\n  text-align: center;\n  font-size: 34px;\n  line-height: 50px;\n}\n.withdrawCon .tips[data-v-35618894]{\n  text-align: center;\n  font-size: 14px;\n  line-height: 2;\n}\n.withdrawCon .pic[data-v-35618894]{\n  position: absolute;\n  width: 100%;\n  left: 0;\n  top:0;\n  display: block;\n}\n.withdrawCon .icon[data-v-35618894]{\n  position: absolute;\n  width: 100%;\n  left: 0;\n  bottom:0;\n  display: block;\n}\n.successIcon[data-v-35618894]{\n  width: 18.8%;\n  margin: 0 auto;\n  border: 5px solid transparent;\n  border-radius: 100%;\n  position: absolute;\n  left: 40.6%;\n  bottom: 6px;\n  z-index: 5;\n}\n.successIcon img[data-v-35618894]{\n  display: block;\n}\n.recordBtn[data-v-35618894]{\n  width: 76%;\n  margin: 70px auto 0;\n  text-align: center;\n  font-size: 18px;\n  line-height: 52px;\n  border-radius: 4px;\n}\n.recordBtn a[data-v-35618894]{\n  display: block;\n  color: #fff;\n  /*background: linear-gradient();*/\n}\n/*提现记录*/\n.withdrawList li[data-v-35618894]{\n  background: #fff;\n  position: relative;\n  padding: 10px 3%;\n}\n.withdrawList li[data-v-35618894]::after{\n  width: 94%;\n  height: 1px;\n  left: 3%;\n  bottom: 0;\n  position: absolute;\n  background: #eeeeee;\n  content: '';\n}\n.withdrawList li .first[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.withdrawList li  .number[data-v-35618894]{\n  font-size: 14px;\n  line-height: 28px;\n  color: #202224;\n}\n.withdrawList li  .state[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n.withdrawList li  .state .icon[data-v-35618894]{\n  width: 24px;\n  display: block;\n  margin-right: 5px;\n}\n.withdrawList li  .state div[data-v-35618894]{\n  font-size: 14px;\n  line-height: 20px;\n}\n.withdrawList li  .state .now[data-v-35618894]{\n  color: #9ea3a9;\n}\n.withdrawList li  .state .success[data-v-35618894]{\n  color: #04ae04;\n}\n.withdrawList li  .state .fail[data-v-35618894]{\n  color: #f23030;\n}\n.withdrawList li .time[data-v-35618894]{\n  font-size: 12px;\n  color: #6e7479;\n  line-height: 24px;\n}\n.withdrawList li .value[data-v-35618894]{\n  font-size: 20px;\n  color: #f23030;\n  line-height: 24px;\n}\n.withdrawText[data-v-35618894]{\n  width: 94%;\n  margin: 15px auto 0;\n  border-radius: 6px;\n  -webkit-box-shadow:0 0 15px rgba(0, 0, 0, .3);\n          box-shadow:0 0 15px rgba(0, 0, 0, .3);\n  background: #fff;\n  padding: 10px;\n}\n.withdrawText .tips[data-v-35618894]{\n  font-size: 14px;\n  line-height: 28px;\n  color: #202224;\n  position: relative;\n  z-index: 2;\n}\n.withdrawText .number[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  font-size: 28px;\n  line-height: 30px;\n  color: #202224;\n  border-bottom: 1px solid #eee;\n  padding: 10px 0;\n  position: relative;\n  z-index: 2;\n}\n.withdrawText .number input[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n  display: block;\n  font-size: 28px;\n  line-height: 30px;\n  background: none;\n  width: 100%;\n}\n.withdrawText .det[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  padding-top: 10px;\n  font-size: 14px;\n  line-height: 24px;\n  color: #828282;\n  position: relative;\n  z-index: 2;\n}\n.withdrawText .det a[data-v-35618894]{\n  color: #4679c0;\n  display: block;\n}\n.withdrawWay[data-v-35618894]{\n  width: 92%;\n  margin: 5px auto 0;\n}\n.withdrawWay .title[data-v-35618894]{\n  padding: 10px;\n  font-size: 14px;\n  line-height: 20px;\n  border-bottom: 1px dashed #e8e8e8;\n}\n.withdrawWay .pay_list[data-v-35618894]{\n  padding-left: 0;\n}\n.withdrawNotice[data-v-35618894]{\n  width: 92%;\n  margin: 20px auto 0;\n}\n.withdrawNotice .title[data-v-35618894]{\n  font-size: 14px;\n  line-height: 24px;\n  color: #6e7479;\n}\n.withdrawNotice .note div[data-v-35618894]{\n  font-size: 12px;\n  line-height: 20px;\n  color: #9ea3a9;\n}\n.goWithdraw[data-v-35618894]{\n  width: 92%;\n  margin: 30px auto 0;\n  font-size: 16px;\n  line-height: 46px;\n  text-align: center;\n  /*background: #f23030;*/\n  color: #fff;\n  border-radius: 4px;\n}\n.profitCon[data-v-35618894]{\n  padding-bottom: 20px;\n}\n.profitCon .detail[data-v-35618894]{\n  position: relative;\n}\n.profitCon .detail .pic[data-v-35618894]{\n  position: absolute;\n  width: 100%;\n  left: 0;\n  bottom: 0;\n  z-index: 0;\n}\n.profitCon .tips[data-v-35618894],.profitCon .number input[data-v-35618894],.profitCon .det[data-v-35618894],.profitCon .det a[data-v-35618894]{\n  color: #fff;\n}\n.profitCon .number[data-v-35618894]{\n  border-bottom: none;\n}\n.profitCon .det a[data-v-35618894]{\n  border:1px solid white;\n  border-radius: 4px;\n  padding: 3px 10px;\n}\n.profitCon .tips[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.profitCon .icon[data-v-35618894]{\n  width: 24px;\n  display: block;\n}\n.profitCon .con[data-v-35618894]{\n  width: 94%;\n  margin: 15px auto 0;\n  border-radius: 6px;\n  -webkit-box-shadow:0 0 15px rgba(0, 0, 0, .1);\n          box-shadow:0 0 15px rgba(0, 0, 0, .1);\n  background: #fff;\n}\n.profitCon .con .title[data-v-35618894]{\n  padding: 0 10px;\n  font-size: 14px;\n  line-height: 40px;\n  color: #202224;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: relative;\n}\n.profitCon .con .title[data-v-35618894]::after{\n  width: 200%;\n  height: 1px;\n  background: #f4f4f4;\n  content: '';\n  position: absolute;\n  -webkit-transform: scale(0.5);\n          transform: scale(0.5);\n  bottom: 0;\n  left: -50%;\n}\n.profitCon .con .title .icon[data-v-35618894]{\n  display: block;\n  margin-right: 8px;\n  width: 20px;\n}\n.profitCon .con .title .text[data-v-35618894]{\n  font-weight: bold;\n}\n.profitCon .con .list[data-v-35618894]{\n  height: 100px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n.profitCon .con .list li[data-v-35618894]{\n  width: 50%;\n  text-align: center;\n}\n.profitCon .con .list li .num[data-v-35618894]{\n  font-size: 24px;\n  line-height: 36px;\n}\n.profitCon .con .list li .state[data-v-35618894]{\n  font-size: 12px;\n  line-height: 24px;\n  color: #8b8b8b;\n}\n.orange[data-v-35618894]{\n  color: #fb8a14;\n}\n.blue[data-v-35618894]{\n  color: #0897ff;\n}\n#myChart[data-v-35618894]{\n  height:200px;\n}\n/*申请代言*/\n.applying .state[data-v-35618894]{\n  position: relative;\n  padding-top: 20px;\n}\n.applying .state .stateIcon[data-v-35618894]{\n  position: absolute;\n  width:14%;\n  left: 43%;\n  top:21%;\n}\n.applying .state .icon[data-v-35618894]{\n  display: block;\n  margin: 0 auto;\n  width: 28%;\n}\n.applying .state .wave[data-v-35618894]{\n  display: block;\n  margin-top: 20px;\n}\n.applying  .title[data-v-35618894]{\n  font-size: 16px;\n  line-height: 20px;\n  padding: 20px 0;\n  color: #202224;\n  font-weight: bold;\n  text-align: center;\n}\n.applying .tips[data-v-35618894]{\n  font-size: 14px;\n  line-height: 22px;\n  color: #666;\n  text-align: center;\n}\n.applying .go[data-v-35618894]{\n  width: 50%;\n  margin: 30px auto 0;\n  border-radius: 30px;\n  font-size: 16px;\n  line-height: 44px;\n  text-align: center;\n}\n.applying .go a[data-v-35618894]{\n  color: #fff;\n  display: block;\n}\n/*收益记录*/\n.incomeCon[data-v-35618894]{\n  width: 94%;\n  margin: 0 auto;\n}\n.incomeCon .totalAll[data-v-35618894]{\n  font-size: 14px;\n  line-height: 24px;\n  color: #202020;\n  margin-top: 5px;\n}\n.incomeCon .totalAll span[data-v-35618894]{\n  margin-left: 30px;\n}\n.incomeCon .dayCon .time[data-v-35618894]{\n  font-size: 14px;\n  line-height: 40px;\n  color: #202224;\n}\n.incomeCon .dayCon .time span[data-v-35618894]{\n  margin-left: 15px;\n  font-size: 16px;\n  font-weight: bold;\n}\n.incomeCon .listGroup li[data-v-35618894]{\n  margin-top: 15px;\n  background: #fff;\n  border-radius: 4px;\n}\n.incomeCon .listGroup li[data-v-35618894]:first-child{\n  margin-top: 0;\n}\n.incomeCon .listGroup li .info[data-v-35618894]{\n  padding: 8px 10px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: relative;\n}\n.incomeCon .listGroup li .info .user[data-v-35618894]{\n  width: 28px;\n  height: 28px;\n  border-radius: 20px;\n  display: block;\n}\n.incomeCon .listGroup li .info .name[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n  margin: 0 10px;\n  font-size: 14px;\n  line-height: 24px;\n  color: #6e7479;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  overflow: hidden;\n}\n.incomeCon .listGroup li .info[data-v-35618894]::after{\n  width: 200%;\n  height: 1px;\n  background: #f4f4f4;\n  content: '';\n  position: absolute;\n  -webkit-transform: scale(0.5);\n          transform: scale(0.5);\n  bottom: 0;\n  left: -50%;\n}\n.incomeCon .listGroup li .det[data-v-35618894]{\n  padding: 10px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n.incomeCon .listGroup li .det .pic[data-v-35618894]{\n  display: block;\n  width: 62px;\n  height: 62px;\n  margin-right: 10px;\n}\n.incomeCon .listGroup li .text[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n.incomeCon .listGroup li .det .title[data-v-35618894]{\n  font-size: 14px;\n  line-height: 20px;\n  color: #202224;overflow: hidden;\n  text-overflow: ellipsis;\n  display: -webkit-box;\n  -webkit-line-clamp: 2;\n  -webkit-box-orient: vertical;\n  height: 40px;\n}\n.incomeCon .listGroup li .data[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  font-size: 12px;\n  line-height: 22px;\n}\n.incomeCon .listGroup li .data .number[data-v-35618894]{\n  font-size: 14px;\n  color: #202224;\n}\n.incomeCon .listGroup li .data .date[data-v-35618894]{\n  color: #9ea3a9;\n}\n/*我的等级*/\n.levelCon[data-v-35618894]{\n  background: #fcfcfc;\n  position: absolute;\n  width: 100%;\n  max-width: 720px;\n  left: 0;\n  top:0;\n  height: 100%;\n  padding-top: 48px;\n}\n.levelDet[data-v-35618894] {\n  width: 96%;\n  margin: 0 auto;\n  position: relative;\n}\n.levelDet .con[data-v-35618894]{\n  position: absolute;\n  top: 5%;\n  left: 3.5%;\n  width: 93%;\n  height: 83%;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n}\n.levelDet .con>div[data-v-35618894]{\n  width: 90%;\n  margin: 0 auto;\n}\n.levelDet .con .title[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  width: 100%;\n  margin-top: 5%;\n}\n.levelDet .con .info[data-v-35618894]{\n  width: 16%;\n}\n.levelDet .con .info .user[data-v-35618894]{\n  width:100%;\n  display: block;\n  border-radius: 30px;\n  margin-right: 10px;\n}\n.levelDet .con  .name[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  overflow: hidden;\n  font-size: 16px;\n  line-height: 2;\n  color: #fff;\n}\n.levelDet .con .level[data-v-35618894]{\n  width: 100%;\n}\n.levelDet .con .record[data-v-35618894]{\n  font-size: 13px;\n  line-height: 2;\n  border: 1px solid #8e6830;\n  border-radius: 20px;\n  text-align: center;\n  padding: 0 10px;\n}\n.levelDet .con .record a[data-v-35618894]{\n  display: block;\n  color: #8e6830;\n}\n.level[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  margin-top: 4%;\n  -webkit-box-pack: start;\n      -ms-flex-pack: start;\n          justify-content: flex-start;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n  overflow: hidden;\n  height: 42px;\n}\n.level li[data-v-35618894]{\n  position: relative;\n  z-index: 1;\n  /*width: 25%;*/\n}\n.level li .number[data-v-35618894]{\n  width: 9vw;\n  display: block;\n  margin: 0 auto;\n  background: #96723a;\n  border-radius: 15px;\n  padding: 3px 0;\n  position: relative;\n  font-size: 3vw;\n  color: #fff;\n  font-weight: bold;\n  text-align: center;\n}\n.level li .number img[data-v-35618894]{\n  width: 24px;\n  display: block;\n  margin: 0 auto;\n}\n/*.level li .number img.current{*/\n/*display: none;*/\n/*}*/\n/*.level li.active .number img.current{*/\n/*display: block;*/\n/*}*/\n/*.level li.active .number img.primary{*/\n/*display: none;*/\n/*}*/\n.level li .level_title[data-v-35618894]{\n  font-size: 12px;\n  color: #96723a;\n  line-height: 24px;\n  text-align: center;\n}\n.level li .number[data-v-35618894]:after{\n  content: '';\n  position: absolute;\n  width: 14vw;\n  height: 3px;\n  background: #96723a;\n  left: 100%;\n  top:50%;\n  margin-top: -1.5px;\n  z-index: 2;\n}\n.level li:last-child .number[data-v-35618894]::after{\n  display: none;\n}\n.level li:last-child.active .number[data-v-35618894]::before{\n  display: none;\n}\n.level li.active .number[data-v-35618894]{\n  background: #fff;\n  color: #96723a;\n}\n.level li.active .level_title[data-v-35618894]{\n  color: #fff;\n}\n.level li.active .number[data-v-35618894]::before{\n  content: '';\n  position: absolute;\n  width: 14vw;\n  height: 3px;\n  background: #fff;\n  left: 100%;\n  top:50%;\n  margin-top: -1.5px;\n  z-index: 3;\n}\n.level li.next:not(.active) .number[data-v-35618894]::before{\n  content: '';\n  position: absolute;\n  width: 7vw;\n  height: 3px;\n  background: #96723a;\n  right: 8vw;\n  top:50%;\n  margin-top: -1.5px;\n  z-index: 3;\n}\n.level li:first-child:last-child .number[data-v-35618894]::before{\n  display: none;\n}\n.representNum[data-v-35618894]{\n  width: 89%;\n  margin: 0 0 0 6%;\n}\n.representNum .con[data-v-35618894],.representNum .det[data-v-35618894]{\n  background: #fff;\n  border-radius: 10px;\n  -webkit-box-shadow:0px 0px 10px rgba(0,0,0,0.1);\n          box-shadow:0px 0px 10px rgba(0,0,0,0.1);\n  padding:3% 4%;\n  margin-top: 15px;\n}\n.representNum .title[data-v-35618894]{\n  padding: 0 5px;\n  font-size: 18px;\n  line-height: 1;\n  color: #202224;\n}\n.representNum .title span[data-v-35618894]{\n  font-size: 12px;\n  color: #666;\n}\n.representNum .con  .numberList[data-v-35618894]{\n}\n.representNum .con  .list[data-v-35618894]{\n  position: relative;\n}\n.representNum .con   .list[data-v-35618894]::after{\n  background: #f9f9fa;\n  content: '';\n  width: 200%;\n  height: 1px;\n  position: absolute;\n  left: -50%;\n  bottom: 0;\n  -webkit-transform: scale(0.5);\n          transform: scale(0.5);\n}\n.representNum .con  .list[data-v-35618894]:last-child::after{\n  display: none;\n}\n.representNum .con  .numberList .list  .tit[data-v-35618894]{\n  font-size: 14px;\n  line-height: 30px;\n  color: #202224;\n}\n.representNum .con  .numberList .list  .progress[data-v-35618894]{\n  width: 100%;\n  height: 5px;\n  background: #b58f3e;\n  position: relative;\n  border-radius: 5px;\n  margin-top: 18px;\n}\n.representNum .con  .numberList .list  .progress .mark[data-v-35618894]{\n  position: absolute;\n  height: 100%;\n  border-radius: 5px;\n  left: 0;\n  top:0;\n  background: #8b6d27;\n}\n.representNum .con  .numberList .list  .progress .mark .num[data-v-35618894]{\n  position:absolute;\n  top:-12px;\n  left: calc(100% - 6px);\n  border-left: 6px solid transparent;\n  border-right: 6px solid transparent;\n  border-top: 6px solid #e1c786;\n}\n.representNum .con  .numberList .list  .progress .mark .num div[data-v-35618894]{\n  font-size: 12px;\n  line-height: 18px;\n  color: #846620;\n  position: absolute;\n  top:-26px;\n  left: 50%;\n  margin-left: -20px;\n  width: auto;\n  min-width: 40px;\n  padding: 0 5px;\n  border: 1px solid #e1c786;\n  border-radius: 4px;\n  text-align: center;\n  background: #efd89a;\n}\n.representNum .con  .numberList .list  .progress .mark .num[data-v-35618894]::after{\n  border-left: 6px solid transparent;\n  border-right: 6px solid transparent;\n  border-top: 6px solid #efd89a;\n  position: absolute;\n  left: -6px;\n  top:-7px;\n  content: '';\n}\n.representNum .con  .numberList .list .numArea[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  color: #a67f2d;\n  font-size: 12px;\n  line-height: 30px;\n}\n.upCon[data-v-35618894]{\n  margin-top: 20px;\n}\n.upCon .con[data-v-35618894]{\n  padding: 0 4%;\n}\n.upCon .con .list[data-v-35618894]{\n  padding: 4% 0;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  font-size: 14px;\n  line-height: 24px;\n  color: #202224;\n}\n.upCon .con .list .tips[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n  margin-right: 20px;\n}\n.upCon .con .list .ope[data-v-35618894]{\n  width: 88px;\n  line-height: 32px;\n  text-align: center;\n  background: -webkit-gradient(linear,left top, right top,from(#d0aa71),to(#e9ce9b));\n  background: linear-gradient(to right,#d0aa71,#e9ce9b);\n  border-radius: 20px;\n  color: #fff;\n}\n.upCon .con .list .ope a[data-v-35618894]{\n  color: #fff;\n  display: block;\n}\n.representNum .det .tit[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  font-size: 14px;\n  line-height: 20px;\n  color: #8b6c26;\n  margin-bottom: 10px;\n}\n.representNum .det .tit .icon[data-v-35618894]{\n  width: 40px;\n  display: block;\n  margin-right: 10px;\n}\n.representNum .det .levelRule[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align:start;\n      -ms-flex-align:start;\n          align-items:flex-start;\n}\n.representNum .det .levelRule .icon[data-v-35618894]{\n  width: 20px;\n  height: 20px;\n  display: block;\n  margin-right: 10px;\n}\n.representNum .det .levelRule .text[data-v-35618894]{\n  font-size: 12px;\n  line-height: 22px;\n  color: #6e7479;\n}\n/*代言*/\n.spokeCon[data-v-35618894]{\n  width: 94%;\n  margin: 10px auto 0;\n}\n.spokeCon .title[data-v-35618894]{\n  font-size: 16px;\n  line-height: 2;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  color: #202224;\n}\n.spokeCon .title div[data-v-35618894]{\n  width: 8px;\n  height: 8px;\n  background: #f23030;\n  border-radius: 6px;\n  margin-right: 10px;\n}\n.spokeCon .det[data-v-35618894]{\n  -webkit-box-shadow: 0px 0px 10px rgba(0,0,0,0.1);\n          box-shadow: 0px 0px 10px rgba(0,0,0,0.1);\n  border-radius: 10px;\n  margin-top: 6px;\n  padding: 3%;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  margin-bottom: 20px;\n}\n.spokeCon .det .text[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n.spokeCon .det .button[data-v-35618894]{\n  margin-left: 8%;\n  width: 82px;\n  line-height: 32px;\n  border-radius: 20px;\n  color: #fff;\n  font-size: 14px;\n  text-align: center;\n}\n.spokeCon .det .button a[data-v-35618894]{\n  color: #fff;\n  display: block;\n}\n.spokeCon .det .tit[data-v-35618894]{\n  font-size: 16px;\n  line-height: 24px;\n  font-weight: bold;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  overflow: hidden;\n}\n.spokeCon .det .article[data-v-35618894]{\n  color: #848484;\n  font-size: 14px;\n  line-height: 22px;\n  margin-top: 3px;\n}\n.spokeTip[data-v-35618894]{\n  padding: 0 6%;\n  font-size: 14px;\n  line-height: 20px;\n}\n/*申请代言人*/\n.applyCon[data-v-35618894]{\n  background: #f6f6f6;\n  position: fixed;\n  width: 100%;\n  max-width: 720px;\n  left: 0;\n  top:0;\n  height: 100%;\n  padding-top: 48px;\n  overflow: auto;\n}\n.applyList[data-v-35618894]{\n  background: #fff;\n  width: 100%;\n  margin-top: 8px;\n}\n.applyList li[data-v-35618894]{\n  padding: 0 3%;\n  position: relative;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.applyList li[data-v-35618894]::after{\n  width: 200%;\n  height: 1px;\n  background: #eeeeee;\n  content: '';\n  position: absolute;\n  -webkit-transform: scale(0.5);\n          transform: scale(0.5);\n  bottom: 0;\n  left: -50%;\n}\n.applyList li .text[data-v-35618894]{\n  width: 80px;\n  text-align: left;\n  font-size: 14px;\n  line-height: 24px;\n  padding: 10px 0;\n}\n.applyList li .info[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n.applyList li .info input[data-v-35618894]{\n  display: block;\n  width: 100%;\n  font-size: 14px;\n  line-height: 24px;\n  padding: 10px 0;\n}\n.applyList li .det[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n.applyList li .det input[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n.applyList li .det .more[data-v-35618894]{\n  width: 6px;\n  display: block;\n  margin-left: 5px;\n}\n.applyBtn[data-v-35618894]{\n  width: 94%;\n  position: absolute;\n  bottom:20px;\n  left: 3%;\n  /*background: #f23030;*/\n  color: #fff;\n  border-radius: 4px;\n  text-align: center;\n  font-size: 16px;\n  line-height: 20px;\n  padding: 12px 0;\n  background: #d8d8d8;\n}\n.list_tab[data-v-35618894]{\n  width: 100%;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: relative;\n  border-bottom: 1px solid #eeeeee;\n  background: #fff;\n}\n.list_tab li[data-v-35618894]{\n  width: 33.3%;\n  font-size: 14px;\n  line-height: 44px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n}\n.list_tab li div[data-v-35618894]{\n  border-bottom: 2px solid transparent;\n}\n.list_tab li.active[data-v-35618894]{\n  color: #f23030;\n}\n.list_tab li.active div[data-v-35618894]{\n  border-bottom: 2px solid #f23030;\n}\n.fanCon[data-v-35618894]{\n  background: #f6f6f6;\n  position: absolute;\n  width: 100%;\n  max-width: 720px;\n  left: 0;\n  top:0;\n  height: 100%;\n  padding-top: 48px;\n}\n.caret[data-v-35618894]{\n  display: inline-block;\n  width: 0;\n  height: 0;\n  margin-left: 2px;\n  vertical-align: middle;\n  border-top: 4px solid #666;\n  border-right: 4px solid transparent;\n  border-left: 4px solid transparent;\n}\n.condition[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  background: #fafafa;\n}\n.condition .list[data-v-35618894]{\n  width: 80%;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n.condition .number[data-v-35618894]{\n  width: 20%;\n  text-align: center;\n  font-size: 14px;\n  line-height: 40px;\n  color: #f23030;\n  position: relative;\n}\n.condition .number[data-v-35618894]::before{\n  width: 1px;\n  height: 100%;\n  content: '';\n  position: absolute;\n  background: #eeeeee;\n  left: 0;\n  top:0;\n  z-index: 2;\n}\n.condition .list li[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  width: 33.3%;\n  font-size: 14px;\n  line-height: 40px;\n}\n.condition .list li .title[data-v-35618894]{\n  font-size: 14px;\n  line-height: 40px;\n  color: #202224;\n}\n.condition .list li div[data-v-35618894] {\n  width: 10px;\n}\n.condition .list li .caret[data-v-35618894] {\n  margin-left: 6px;\n}\n.condition .list li .up[data-v-35618894] {\n  -webkit-transform: rotate(180deg);\n          transform: rotate(180deg);\n  margin-bottom: 2px;\n}\n.condition .list li .caret[data-v-35618894] {\n  float: left;\n}\n.fansList[data-v-35618894]{\n  width: 94%;\n  margin: 0 auto;\n}\n.fansList li[data-v-35618894]{\n  margin: 10px auto 0;\n  padding: 3.5% 0;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: relative;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.fansList li[data-v-35618894]::after{\n  width: calc(100% - 24px);\n  content: '';\n  position: absolute;\n  left: 12px;\n  top:0;\n  background: #fff;\n  border-radius: 8px;\n  z-index: 1;\n  height: 100%;\n}\n.fansList li>div[data-v-35618894]{\n  position: relative;\n  z-index: 2;\n}\n.fansList .info[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n.fansList li .user[data-v-35618894]{\n  width: 50px;\n  height: 50px;\n  border-radius: 30px;\n  display: block;\n  margin-right: 15px;\n  position: relative;\n  z-index: 2;\n}\n.fansList li .text[data-v-35618894]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n  position: relative;\n  z-index: 2;\n}\n.fansList li .text>div[data-v-35618894]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  /*justify-content: space-between;*/\n}\n.fansList li  .name[data-v-35618894]{\n  font-size: 14px;\n  line-height: 22px;\n  color: #202224;\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n.fansList li  .price[data-v-35618894]{\n  margin-right: 20px;\n  font-size: 12px;\n  ;line-height: 20px;\n}\n.fansList li  .time[data-v-35618894]{\n  color:#9ea3a9;\n  font-size: 12px;\n  line-height: 22px;\n}\n.fansList li  .number[data-v-35618894]{\n  font-size: 10px;\n  line-height: 20px;\n  background: #f23030;\n  border-radius: 4px;\n  padding: 1px 6px;\n  color: #fff;\n  float: right;\n}\n.fansList li  .number span[data-v-35618894]{\n  font-size: 14px;\n}\n.fansList li  .number  img[data-v-35618894]{\n  width: 11px;\n  display: block;\n  margin-right: 5px;\n}\n.levelCon[data-v-35618894] {\n  background: #fff;\n}\n.levelCon .btn[data-v-35618894]{\n  width: 80%;\n  font-size: 14px;\n  line-height: 40px;\n  margin: 20px auto 0;\n  border-radius: 4px;\n  color: #fff;\n  background: #f23030;\n  text-align: center;\n}\n\n",""])}});