webpackJsonp([114],{CuQc:function(t,n,i){var a=i("rQcL");"string"==typeof a&&(a=[[t.i,a,""]]),a.locals&&(t.exports=a.locals);i("rjj0")("9d2fd462",a,!0,{})},bUne:function(t,n,i){"use strict";Object.defineProperty(n,"__esModule",{value:!0});var a=i("c2Ch"),e={name:"IntegralTask",data:function(){return{info:{state:0,integral:0},phone:{state:0,integral:0},third:{state:0,integral:0},sign:{state:0,integral:0},evaluate:{state:0,integral:0,number:1},share:{state:0,integral:0,number:1},adv:{integral:0},adv_info:{},isShow:!0,task_status:[{status:!0},{status:!0},{status:!0}]}},activated:function(){this.getData(),this.commonJs.changeColor()},methods:{getData:function(){var t=this;Object(a._81)().then(function(n){t.$nextTick(function(){console.log(n.result),this.info=n.result.info,this.phone=n.result.phone,this.third=n.result.third_party,this.sign=n.result.sign,this.evaluate=n.result.evaluate,this.share=n.result.share,this.adv=n.result.adv,this.adv_info=n.adv_info,this.commonJs.changeColor()})})},showDet:function(t){this.task_status[t].status=!this.task_status[t].status},onIntegralAdv:function(){switch(adBrowseInc({adv_id:this.adv_info.adv_id}).then(function(t){}),this.adv_info.type){case 0:break;case 1:this.$router.push({name:"GoodDetail",query:{goods_id:this.adv_info.content}});break;case 2:this.$router.push({name:"ShopDetail",params:{store_id:this.adv_info.content}})}}}},s={render:function(){var t=this,n=t.$createElement,i=t._self._c||n;return i("div",{staticClass:"taskCon"},[i("head-title",{attrs:{title:"积分任务"}}),t._v(" "),i("div",{staticClass:"integral_task"},[null!=t.adv_info?i("div",{staticClass:"inter_pic"},[i("a",{on:{click:t.onIntegralAdv}},[i("div",{staticClass:"pic_ratio",staticStyle:{"--aspect-ratio":"3.78/1"}},[i("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.adv_info.file,expression:"adv_info.file"}],staticClass:"pic",attrs:{title:"",alt:""}})])])]):t._e(),t._v(" "),i("div",{staticClass:"task_con"},[i("div",{staticClass:"task_title",on:{click:function(n){t.showDet(0)}}},[t._m(0),t._v(" "),i("img",{staticClass:"icon",class:{more:!t.isShow},style:t.task_status[0].status?"":"transform: rotate(180deg)",attrs:{src:"/static/img/inter_icon.png"}})]),t._v(" "),i("ul",{directives:[{name:"show",rawName:"v-show",value:t.task_status[0].status,expression:"task_status[0].status"}],staticClass:"task_list",class:{hide:!t.isShow}},[i("li",[i("div",{staticClass:"con"},[i("div",{staticClass:"task_tips"},[t._v("完善个人信息")]),t._v(" "),i("div",{staticClass:"tips"},[t._v("补全个人信息"),i("span",{staticClass:"textActive"},[t._v("+"+t._s(t.info.integral))]),t._v("积分")]),t._v(" "),0==t.info.state?i("div",{staticClass:"go_btn bgActive"},[i("router-link",{attrs:{to:{name:"Personal"}}},[t._v("去完成")])],1):i("div",{staticClass:"go_btn done"},[i("a",{attrs:{href:"javascript:;"}},[t._v("已完成")])])])]),t._v(" "),i("li",[i("div",{staticClass:"con"},[i("div",{staticClass:"task_tips"},[t._v("绑定手机号")]),t._v(" "),i("div",{staticClass:"tips"},[t._v("绑定手机号"),i("span",{staticClass:"textActive"},[t._v("+"+t._s(t.phone.integral))]),t._v("积分")]),t._v(" "),0==t.phone.state?i("div",{staticClass:"go_btn bgActive"},[i("router-link",{attrs:{to:{name:"BindPhone"}}},[t._v("去绑定")])],1):i("div",{staticClass:"go_btn done"},[i("a",{attrs:{href:"javascript:;"}},[t._v("已绑定")])])])]),t._v(" "),i("li",[i("div",{staticClass:"con"},[i("div",{staticClass:"task_tips"},[t._v("绑定第三方社交账号")]),t._v(" "),i("div",{staticClass:"tips"},[t._v("将本账号与第三方账号管理"),i("span",{staticClass:"textActive"},[t._v("+"+t._s(t.third.integral))]),t._v("积分")]),t._v(" "),0==t.third.state?i("div",{staticClass:"go_btn bgActive"},[i("router-link",{attrs:{to:{name:"AccountRelation"}}},[t._v("去绑定")])],1):i("div",{staticClass:"go_btn done"},[i("a",{attrs:{href:"javascript:;"}},[t._v("已绑定")])])])])])]),t._v(" "),i("div",{staticClass:"task_con",on:{click:function(n){t.showDet(1)}}},[i("div",{staticClass:"task_title"},[t._m(1),t._v(" "),i("img",{staticClass:"icon",style:t.task_status[1].status?"":"transform: rotate(180deg)",attrs:{src:"/static/img/inter_icon.png"}})]),t._v(" "),i("ul",{directives:[{name:"show",rawName:"v-show",value:t.task_status[1].status,expression:"task_status[1].status"}],staticClass:"task_list"},[i("li",[i("div",{staticClass:"con"},[i("div",{staticClass:"task_tips"},[t._v("购物")]),t._v(" "),i("div",{staticClass:"tips"},[t._v("购物越多（实际支付金额），获得积分越多")]),t._v(" "),i("div",{staticClass:"go_btn bgActive"},[i("router-link",{attrs:{to:{path:"/"}}},[t._v("去购物")])],1)])])])]),t._v(" "),i("div",{staticClass:"task_con"},[i("div",{staticClass:"task_title",on:{click:function(n){t.showDet(2)}}},[t._m(2),t._v(" "),i("img",{staticClass:"icon",style:t.task_status[2].status?"":"transform: rotate(180deg)",attrs:{src:"/static/img/inter_icon.png"}})]),t._v(" "),i("ul",{directives:[{name:"show",rawName:"v-show",value:t.task_status[2].status,expression:"task_status[2].status"}],staticClass:"task_list"},[1==t.Global.show_switch.is_sign_in?i("li",[i("div",{staticClass:"con"},[i("div",{staticClass:"task_tips"},[t._v("每日签到")]),t._v(" "),i("div",{staticClass:"tips"},[t._v("每日签到"),i("span",{staticClass:"textActive"},[t._v("+"+t._s(t.sign.integral))]),t._v("积分")]),t._v(" "),0==t.sign.state?i("div",{staticClass:"go_btn bgActive"},[i("router-link",{attrs:{to:{name:"Integral"}}},[t._v("去签到")])],1):i("div",{staticClass:"go_btn done"},[i("a",{attrs:{href:"javascript:;"}},[t._v("已签到")])])])]):t._e(),t._v(" "),i("li",[i("div",{staticClass:"con"},[i("div",{staticClass:"task_tips"},[t._v("评价商品")]),t._v(" "),i("div",{staticClass:"tips"},[t._v("发表1次评价"),i("span",{staticClass:"textActive"},[t._v("+"+t._s(t.evaluate.integral))]),t._v("积分（每日最多"+t._s(t.evaluate.integral*t.evaluate.number)+"分）")]),t._v(" "),0==t.evaluate.state?i("div",{staticClass:"go_btn bgActive"},[i("router-link",{attrs:{to:{name:"CommentSuccess"}}},[t._v("去评价")])],1):i("div",{staticClass:"go_btn done"},[i("a",{attrs:{href:"javascript:;"}},[t._v("已评价")])])])]),t._v(" "),i("li",[i("div",{staticClass:"con"},[i("div",{staticClass:"task_tips"},[t._v("分享商品或活动")]),t._v(" "),i("div",{staticClass:"tips"},[t._v("将链接分享到其他平台"),i("span",{staticClass:"textActive"},[t._v("+"+t._s(t.share.integral))]),t._v("积分（每日最多"+t._s(t.share.integral*t.share.number)+"分）")]),t._v(" "),0==t.share.state?i("div",{staticClass:"go_btn bgActive"},[i("router-link",{attrs:{to:{path:"/"}}},[t._v("去分享")])],1):i("div",{staticClass:"go_btn done"},[i("a",{attrs:{href:"javascript:;"}},[t._v("已分享")])])])]),t._v(" "),i("li",[i("div",{staticClass:"con"},[i("div",{staticClass:"task_tips"},[t._v("浏览广告")]),t._v(" "),i("div",{staticClass:"tips"},[t._v("查看一条广告"),i("span",{staticClass:"textActive"},[t._v("+"+t._s(t.adv.integral))]),t._v("积分")]),t._v(" "),i("div",{staticClass:"go_btn bgActive"},[i("router-link",{attrs:{to:{name:"HotList"}}},[t._v("去浏览")])],1)])])])])])],1)},staticRenderFns:[function(){var t=this.$createElement,n=this._self._c||t;return n("div",{staticClass:"title"},[this._v("完善账户\n          "),n("div",{staticClass:"line bgActive"})])},function(){var t=this.$createElement,n=this._self._c||t;return n("div",{staticClass:"title"},[this._v("消费购物\n          "),n("div",{staticClass:"line bgActive"})])},function(){var t=this.$createElement,n=this._self._c||t;return n("div",{staticClass:"title"},[this._v("更多互动\n          "),n("div",{staticClass:"line bgActive"})])}]};var l=i("VU/8")(e,s,!1,function(t){i("CuQc")},"data-v-590d9b5f",null);n.default=l.exports},rQcL:function(t,n,i){var a=i("kxFB");(t.exports=i("FZ+f")(!1)).push([t.i,"\n.inter_list[data-v-590d9b5f] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n}\n.inter_list li[data-v-590d9b5f] {\n  width: 25%;\n  padding: 10px 0;\n}\n.inter_list li a[data-v-590d9b5f] {\n  display: block;\n}\n.inter_list li img[data-v-590d9b5f] {\n  width: 60%;\n  display: block;\n  margin: 0 auto;\n}\n.inter_list li .title[data-v-590d9b5f] {\n  font-size: 13px;\n  line-height: 22px;\n  text-align: center;\n  color: #202224;\n}\n.inter_head[data-v-590d9b5f] {\n  background: #fff;\n  border-bottom: 1px solid #eee;\n  margin-bottom: 8px;\n  padding: 20px 0;\n}\n.inter_head > div[data-v-590d9b5f] {\n  overflow: hidden;\n  width: 94%;\n  margin: 0 auto;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: relative;\n  height: 50px;\n}\n.inter_head.pri .user[data-v-590d9b5f] {\n  width: 50px;\n  height: 50px;\n  border-radius: 100%;\n  margin-right: 5px;\n}\n.inter_head .sign[data-v-590d9b5f] {\n  font-size: 14px;\n  line-height: 30px;\n  border-radius: 20px;\n  color: #fff;\n  width: 80px;\n  position: absolute;\n  right: 0;\n  top: 50%;\n  margin-top: -14px;\n  text-align: center;\n}\n.inter_head.pri .info .name[data-v-590d9b5f] {\n  font-size: 16px;\n  line-height: 20px;\n  color: #202224;\n  width: 100px;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  overflow: hidden;\n}\n.inter_head.pri .inter[data-v-590d9b5f] {\n  font-size: 14px;\n  line-height: 16px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: end;\n      -ms-flex-align: end;\n          align-items: flex-end;\n}\n.inter_head.pri .inter img[data-v-590d9b5f] {\n  width: 20px;\n  margin-right: 10px;\n}\n.inter_head.new .sign[data-v-590d9b5f] {\n  background: #fff;\n  line-height: 28px;\n}\n.inter_head.new .my[data-v-590d9b5f] {\n  font-size: 16px;\n  line-height: 22px;\n}\n.inter_head.new .my a[data-v-590d9b5f]{\n  color: #202224;\n}\n.inter_head.new .day[data-v-590d9b5f] {\n  color: #6e7479;\n  font-size: 12px;\n  line-height: 18px;\n}\n.inter_title[data-v-590d9b5f] {\n  background: #fff;\n  padding: 8px 3%;\n}\n.inter_det[data-v-590d9b5f]{\n  position: fixed;\n  top:48px;\n  left: 0;\n  width: 100%;\n  z-index: 3;\n}\n.inter_title ul[data-v-590d9b5f] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  border-radius: 4px;\n  overflow: hidden;\n}\n.inter_title ul li[data-v-590d9b5f] {\n  width: 50%;\n  text-align: center;\n  font-size: 14px;\n  line-height: 30px;\n  background: #fff;\n  color: #fff;\n  border-top: none !important;\n  border-right: none !important;\n  border-bottom: none !important;\n}\n.inter_title ul li[data-v-590d9b5f]:first-child {\n  border-left: none;\n}\n.inter_title ul li.active[data-v-590d9b5f] {\n  color: #fff !important;\n}\n.inter_cate[data-v-590d9b5f] {\n  overflow: auto;\n  width: 100%;\n  background: #fff;\n}\n.inter_cate[data-v-590d9b5f]::-webkit-scrollbar {\n  display: none;\n}\n.inter_cate ul[data-v-590d9b5f] {\n  font-size: 0;\n  white-space: nowrap;\n  overflow-x: auto;\n  overflow-y: hidden;\n}\n.inter_cate ul li[data-v-590d9b5f] {\n  font-size: 14px;\n  line-height: 40px;\n  display: inline-block;\n  width: 25%;\n  text-align: center;\n}\n.inter_cate ul li a[data-v-590d9b5f] {\n  color: #202224;\n  text-align: center;\n  display: block;\n}\n.inter_cate ul li .txt[data-v-590d9b5f] {\n  display: inline-block;\n  position: relative;\n}\n.inter_cate ul li .line[data-v-590d9b5f] {\n  width: 100%;\n  height: 2px;\n  position: absolute;\n  left: 0;\n  bottom: 0;\n}\n.cate_list_group li .exchange[data-v-590d9b5f] {\n  background: url("+a(i("JxTv"))+") no-repeat;\n  background-size: 100% 100%;\n  font-size: 13px;\n  text-align: center;\n  line-height: 22px;\n  height: 26px;\n  color: #fff;\n  width: 64px;\n  position: absolute;\n  right: 0;\n  top: 0;\n}\n.cate_list_group .goods_con .inter[data-v-590d9b5f] {\n  font-size: 12px;\n  line-height: 20px;\n  padding-bottom: 5px;\n  color: #8d8d8d;\n  position: relative;\n}\n.cate_list_group .goods_con .inter span[data-v-590d9b5f] {\n  font-size: 16px;\n}\n.cate_list_group  .save[data-v-590d9b5f] {\n  color: #fff;\n  background: #17cd91;\n  padding: 0 5px;\n  font-size: 12px;\n  line-height: 16px;\n  position: absolute;\n  right: 0;\n  bottom: 0;\n}\n.inter_title.inter_det[data-v-590d9b5f] {\n  background: #f6f6f6;\n}\n.inter_det_list[data-v-590d9b5f] {\n  background: #fff;\n  padding: 0 3%;\n}\n.inter_det_list li[data-v-590d9b5f] {\n  border-bottom: 1px solid #eee;\n  padding: 10px 0 8px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.inter_det_list li .act[data-v-590d9b5f] {\n  font-size: 14px;\n  line-height: 24px;\n  color: #202224;\n}\n.inter_det_list li .time[data-v-590d9b5f] {\n  font-size: 12px;\n  line-height: 20px;\n  color: #9ea3a9;\n}\n.inter_det_list li .det[data-v-590d9b5f]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n.inter_det_list li .num[data-v-590d9b5f] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  color: #202224;\n  font-size: 16px;\n  line-height: 24px;\n  overflow: hidden;\n  margin-left: 10px;\n}\n.inter_det_list li .num .iconPar[data-v-590d9b5f] {\n  width: 16px;\n  overflow: hidden;\n  margin-right: 5px;\n}\n.inter_det_list li .num img[data-v-590d9b5f] {\n  width: 16px;\n  display: block;\n}\n.inter_det_list li .num .iconColor[data-v-590d9b5f] {\n  border-right: 16px solid transparent;\n  position: relative;\n}\n.inter_det_list li .num .inter_num[data-v-590d9b5f]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n}\n.month_select[data-v-590d9b5f] {\n  position: absolute;\n  font-size: 13px;\n  line-height: 20px;\n  color: #6e7479;\n  right: 3%;\n  top: 50%;\n  margin-top: -10px;\n  border-radius: 0 !important;\n  border: none !important;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n\n/*积分任务*/\n.integral_task[data-v-590d9b5f] {\n  padding-bottom: 8px;\n}\n.inter_pic img[data-v-590d9b5f] {\n  display: block;\n}\n.integral_task .task_con[data-v-590d9b5f] {\n  margin-top: 8px;\n  background: #fff;\n}\n.integral_task .task_title[data-v-590d9b5f] {\n  padding: 10px 3%;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.integral_task .task_title .title[data-v-590d9b5f] {\n  font-size: 15px;\n  line-height: 24px;\n  color: #202224;\n  position: relative;\n  padding-left: 10px;\n}\n.integral_task .task_title .title .line[data-v-590d9b5f] {\n  width: 3px;\n  height: 14px;\n  position: absolute;\n  left: 0;\n  top: 50%;\n  margin-top: -7px;\n}\n.integral_task .task_title .icon[data-v-590d9b5f] {\n  width: 16px;\n  display: block;\n  -webkit-transition: all 0.5s ease;\n  transition: all 0.5s ease;\n}\n.integral_task .task_title .icon.more[data-v-590d9b5f] {\n  -webkit-transform: rotate(180deg);\n          transform: rotate(180deg);\n  -webkit-transition: all 0.5s ease;\n  transition: all 0.5s ease;\n}\n.integral_task .task_con .task_list[data-v-590d9b5f] {\n  border-top: 1px solid #EEEEEE;\n}\n.integral_task .task_con .task_list li[data-v-590d9b5f] {\n  border-bottom: 1px solid #EEEEEE;\n  padding: 12px 3% 10px;\n}\n.integral_task .task_con .task_list li .con[data-v-590d9b5f] {\n  padding-right: 80px;\n  position: relative;\n}\n.integral_task .task_con .task_list li .con .go_btn[data-v-590d9b5f] {\n  width: 70px;\n  font-size: 13px;\n  line-height: 28px;\n  text-align: center;\n  position: absolute;\n  right: 0;\n  top: 50%;\n  margin-top: -14px;\n  border-radius: 20px;\n}\n.integral_task .task_con .task_list li .con .go_btn a[data-v-590d9b5f] {\n  color: #fff;\n  display: block;\n}\n.integral_task .task_con .task_list li .con .go_btn.done[data-v-590d9b5f] {\n  background: #D3D4D5 !important;\n}\n.task_list li .task_tips[data-v-590d9b5f] {\n  font-size: 14px;\n  line-height: 18px;\n  color: #202224;\n}\n.task_list li .tips[data-v-590d9b5f] {\n  font-size: 13px;\n  line-height: 18px;\n  color: #9EA3A9;\n  margin-top: 5px;\n}\n.task_list.hide[data-v-590d9b5f] {\n  display: none;\n}\n.growth_pic[data-v-590d9b5f] {\n  display: block;\n}\n.growth_num[data-v-590d9b5f] {\n  position: relative;\n}\n.growth_num .pic[data-v-590d9b5f] {\n  display: block;\n}\n.growth_num .time[data-v-590d9b5f] {\n  font-size: 14px;\n  line-height: 20px;\n  text-align: center;\n  color: #202224;\n  position: absolute;\n  width: 100%;\n  bottom: 6%;\n  left: 0;\n  z-index: 2;\n}\n.growth_num .per_num[data-v-590d9b5f] {\n  position: absolute;\n  z-index: 2;\n  font-size: 14vw;\n  width: 100%;\n  text-align: center;\n  bottom: 26%;\n}\n.taskCon[data-v-590d9b5f] {\n  margin-top: 48px;\n  min-height: calc(100vh - 48px);\n  background: #f6f6f6;\n}\n\n",""])}});