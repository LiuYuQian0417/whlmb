webpackJsonp([140],{CnaW:function(n,e,t){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var i=t("c2Ch"),a={name:"EditPayPsw",data:function(){return{old_psw:"",new_psw:"",confirm_psw:""}},methods:{submit:function(){var n=this;""!=this.old_psw?""!=this.new_psw?""!=this.confirm_psw?this.new_psw==this.confirm_psw?Object(i._151)({old_password:this.old_psw,pay_password:this.new_psw}).then(function(e){n.$Toast.toast("修改密码成功"),n.$skip.back()}):this.$Toast.toast("两次新密码不一致"):this.$Toast.toast("请输入确认密码"):this.$Toast.toast("请输入新密码"):this.$Toast.toast("请输入旧密码")}}},o={render:function(){var n=this,e=n.$createElement,t=n._self._c||e;return t("div",[t("head-title",{attrs:{title:"修改支付密码",right_text:"完成"},on:{rightClick:n.submit}}),n._v(" "),t("div",{staticClass:"wrap"},[t("ul",{staticClass:"account_list"},[t("li",[t("input",{directives:[{name:"model",rawName:"v-model",value:n.old_psw,expression:"old_psw"}],staticClass:"text",attrs:{type:"password",maxlength:"6",onkeyup:"this.value=this.value.replace(/\\D/g,'')",placeholder:"输入旧的支付密码"},domProps:{value:n.old_psw},on:{input:function(e){e.target.composing||(n.old_psw=e.target.value)}}})]),n._v(" "),t("li",[t("input",{directives:[{name:"model",rawName:"v-model",value:n.new_psw,expression:"new_psw"}],staticClass:"text",attrs:{type:"password",maxlength:"6",onkeyup:"this.value=this.value.replace(/\\D/g,'')",placeholder:"设置新的支付密码"},domProps:{value:n.new_psw},on:{input:function(e){e.target.composing||(n.new_psw=e.target.value)}}})]),n._v(" "),t("li",[t("input",{directives:[{name:"model",rawName:"v-model",value:n.confirm_psw,expression:"confirm_psw"}],staticClass:"text",attrs:{type:"password",maxlength:"6",onkeyup:"this.value=this.value.replace(/\\D/g,'')",placeholder:"确认新的支付密码"},domProps:{value:n.confirm_psw},on:{input:function(e){e.target.composing||(n.confirm_psw=e.target.value)}}})])])])],1)},staticRenderFns:[]};var l=t("VU/8")(a,o,!1,function(n){t("LElf")},"data-v-24ebc811",null);e.default=l.exports},LElf:function(n,e,t){var i=t("sXaS");"string"==typeof i&&(i=[[n.i,i,""]]),i.locals&&(n.exports=i.locals);t("rjj0")("fe6487ca",i,!0,{})},sXaS:function(n,e,t){var i=t("kxFB");(n.exports=t("FZ+f")(!1)).push([n.i,"\n.personal_con[data-v-24ebc811] {\n  /*background: url(\"../img/my.png\") no-repeat center top;*/\n  /*background-size: 100% auto;*/\n  padding-bottom: 65px;\n  position: relative;\n  z-index: 2;\n}\n.personalCon[data-v-24ebc811]{\n  position: relative;\n}\n.personalCon .bg[data-v-24ebc811]{\n  position: relative;\n  /*width: 100%;*/\n  height: 200px;\n  overflow: hidden;\n}\n.personalCon .top[data-v-24ebc811]{\n  width: 200px;\n  height: 200px;\n  -webkit-transform:translate(-48px,-108px);\n          transform:translate(-48px,-108px);\n  border-radius: 50%;\n}\n.personalCon .bottom[data-v-24ebc811]{\n  width: 150px;\n  height: 150px;\n  -webkit-transform:translate(287px,-140px);\n          transform:translate(287px,-140px);\n  border-radius: 50%;\n}\n.personalCon .bg .per_head[data-v-24ebc811] {\n  position: absolute;\n  left: 0;\n  top:0;\n  width: 97%;\n  margin: 0 auto;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: end;\n      -ms-flex-pack: end;\n          justify-content: flex-end;\n  padding: 15px 0;\n}\n.personalCon .bg .per_head img[data-v-24ebc811] {\n  width: 26px;\n  display: block;\n}\n.personalCon .bg .per_head a[data-v-24ebc811]:nth-child(1){\n  margin-right: 15px;\n}\n.personalCon .bg .per_head a[data-v-24ebc811]{\n  position: relative;\n}\n.personalCon .bg .per_head .num[data-v-24ebc811]{\n  position: absolute;\n  min-width: 12px;\n  line-height: 12px;\n  font-size: 10px;\n  background: #fff;\n  padding: 0 3px;\n  border-radius: 10px;\n  top:-3px;\n  right:0px;\n  text-align: center;\n}\n.personal_con .con[data-v-24ebc811] {\n  width: 100%;\n  margin: 0 auto 10px;\n  -webkit-box-shadow: 0 0 2px 1px #E7E7E7;\n          box-shadow: 0 0 2px 1px #E7E7E7;\n  background: #fff;\n  /*border-radius: 2px;*/\n}\n.personal_con .per_info[data-v-24ebc811] {\n  width: 92%;\n  margin: 0 auto;\n  padding: 15px 0;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.personal_con .per_info .info_state[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n.personal_con .per_info .info_state .user_icon[data-v-24ebc811] {\n  display: block;\n  width: 54px;\n  height: 54px;\n  margin-right: 10px;\n  border-radius: 100%;\n}\n.personal_con .per_info .card[data-v-24ebc811] {\n  height: 48px;\n  display: block;\n}\n.personal_con .per_info .user_ope[data-v-24ebc811] {\n  font-size: 16px;\n  color: #202224;\n  line-height: 20px;\n  font-weight: bold;\n}\n.personal_con .per_info .user_ope a[data-v-24ebc811] {\n  color: #202224;\n}\n.personal_con .per_info .info .name[data-v-24ebc811] {\n  font-size: 15px;\n  line-height: 20px;\n  padding: 3px 0;\n  width: 120px;\n  overflow: hidden;\n  white-space: nowrap;\n}\n.personal_con .per_info .info .level_icon[data-v-24ebc811] {\n  height: 20px;\n}\n.per_list[data-v-24ebc811] {\n  width: 100%;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n}\n.per_list li[data-v-24ebc811] {\n  width: 25%;\n  padding-bottom: 10px;\n}\n.per_list li a[data-v-24ebc811] {\n  width: 100%;\n}\n.per_list li .icon[data-v-24ebc811] {\n  width: 26px;\n  display: block;\n  margin: 0 auto;\n}\n.per_list li .text[data-v-24ebc811] {\n  text-align: center;\n  font-size: 13px;\n  line-height: 24px;\n  margin-top: 5px;\n  color: #202224;\n}\n.per_list li .num[data-v-24ebc811] {\n  font-size: 16px;\n  font-weight: bold;\n  text-align: center;\n  line-height: 24px;\n  color: #202224;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  overflow: hidden;\n}\n.personal_con .con .title[data-v-24ebc811] {\n  font-size: 16px;\n  line-height: 50px;\n  width: 92%;\n  margin: 0 auto 15px;\n  /*border-bottom: 1px solid #eee;*/\n  position: relative;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  /*font-weight: bold;*/\n}\n.personal_con .con .title .level[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  height: 18px;\n  padding: 0 5px;\n  margin-left: 10px;\n  /*background: url(\"../img/icon44.png\") no-repeat;*/\n  background:-webkit-gradient(linear,left top, right top,from(#ff9400),to(#ff7617));\n  background:linear-gradient(to right,#ff9400,#ff7617);\n  border-radius: 0 10px 10px 10px;\n  -webkit-box-shadow: 0 0 10px 0 rgba(255, 148, 0,.4);\n          box-shadow: 0 0 10px 0 rgba(255, 148, 0,.4);\n  background-size:100% 100%;\n  font-size: 9px;\n  line-height: 16px;\n  text-align: center;\n  color: #fff;\n}\n.personal_con .con .title .level a[data-v-24ebc811]{\n  color: #fff;\n  display: block;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  overflow: hidden;\n}\n.personal_con .con .title[data-v-24ebc811]::after {\n  /*width: 200%;*/\n  /*height: 1px;*/\n  /*background: #eee;*/\n  /*position: absolute;*/\n  /*left: -50%;*/\n  /*bottom: 0;*/\n  /*content: '';*/\n  /*transform: scale(0.5);*/\n}\n.wallet_list .text[data-v-24ebc811] {\n  color: #6e7479 !important;\n}\n.wallet_ope[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  position: relative;\n}\n.wallet_ope[data-v-24ebc811]::before {\n  width: 184%;\n  height: 1px;\n  background: #eee;\n  top: 0;\n  left: -42%;\n  content: '';\n  position: absolute;\n  -webkit-transform: scale(0.5);\n          transform: scale(0.5);\n}\n.wallet_ope[data-v-24ebc811]::after {\n  width: 1px;\n  height: 16px;\n  background: #eee;\n  top: 50%;\n  margin-top: -8px;\n  left: 50%;\n  content: '';\n  position: absolute;\n}\n.wallet_ope li[data-v-24ebc811] {\n  width: 50%;\n}\n.wallet_ope li a[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  padding: 12px 0;\n}\n.wallet_ope li .icon[data-v-24ebc811] {\n  width: 26px;\n  display: block;\n  margin-right: 10px;\n  height: 26px;\n  overflow: hidden;\n}\n.wallet_ope li .iconColor[data-v-24ebc811]{\n  border-right: 26px solid transparent;\n  position: relative;\n}\n.wallet_ope li .text[data-v-24ebc811] {\n  font-size: 15px;\n  line-height: 26px;\n  color: #202224;\n}\n.helper_list .icon[data-v-24ebc811] {\n  width: 36px !important;\n}\n/*个人资料*/\n.perInfoList[data-v-24ebc811] {\n  background: #fff;\n  padding-left: 3%;\n}\n.perInfoList li[data-v-24ebc811] {\n  border-bottom: 1px solid #eee;\n  padding: 10px 3% 10px 0;\n}\n.perInfoList li a[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  padding-right: 20px;\n  height: 100%;\n  width: 100%;\n  position: relative;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.perInfoList li a .more[data-v-24ebc811] {\n  width: 7px;\n  position: absolute;\n  right: 0;\n  top: 50%;\n  margin-top: -6.5px;\n}\n.perInfoList li .tip[data-v-24ebc811] {\n  font-size: 16px;\n  line-height: 30px;\n  color: #202224;\n}\n.perInfoList li .user_icon[data-v-24ebc811] {\n  width: 60px;\n  height: 60px;\n  display: block;\n  border-radius: 50px;\n}\n.perInfoList li .code_icon[data-v-24ebc811] {\n  width: 24px;\n  display: block;\n}\n.perInfoList li .info[data-v-24ebc811] {\n  font-size: 14px;\n  color: #9ea3a9;\n  line-height: 30px;\n}\n.user_pic[data-v-24ebc811], .user_sex[data-v-24ebc811] {\n  position: absolute;\n  bottom: 0;\n  left: 0;\n  width: 100%;\n  background: #f6f6f6;\n  display: none;\n}\n.user_pic li[data-v-24ebc811], .user_sex li[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 42px;\n  color: #202224;\n  border-bottom: 1px solid #eee;\n  background: #fff;\n  text-align: center;\n}\n.user_pic li.cancel[data-v-24ebc811], .user_sex li.cancel[data-v-24ebc811] {\n  border-bottom: none;\n  margin-top: 8px;\n}\n.nickname[data-v-24ebc811] {\n  /*padding-left: 3%;*/\n  background: #fff;\n  position: relative;\n  width: 97%;\n}\n.nickname input[data-v-24ebc811] {\n  border-bottom: 1px solid #eee;\n  display: block;\n  width: 100%;\n  font-size: 14px;\n  line-height: 20px;\n  padding: 10px 10px;\n}\n.nickname_tip[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 20px;\n  width: 94%;\n  margin: 10px auto 0;\n  color: #9ea3a9;\n}\n.save_name[data-v-24ebc811] {\n  width: 100%;\n  padding: 0 3% 10px;\n}\n.save_name a[data-v-24ebc811] {\n  display: block;\n  color: #fff;\n  font-size: 16px;\n  line-height: 42px;\n  border-radius: 4px;\n  text-align: center;\n  font-weight: bold;\n}\n.target-fix[data-v-24ebc811] {\n  height: 48px !important;\n}\n.vip_head[data-v-24ebc811] {\n  background: none;\n}\n.vip_head .small[data-v-24ebc811] {\n  color: #fff!important;\n}\n.vip_card[data-v-24ebc811] {\n  width: 94%;\n  border-radius: 8px;\n  overflow: hidden;\n  background: #fff;\n  margin: 2% auto 0;\n  -webkit-transition: all 0.3s ease;\n  transition: all 0.3s ease;\n}\n.vip_card .title[data-v-24ebc811] {\n  font-size: 16px;\n  line-height: 24px;\n  padding: 12px 5%;\n  background: #F3F3F3;\n}\n.card_con[data-v-24ebc811] {\n  padding: 5%;\n}\n.card_con .card_info[data-v-24ebc811] {\n  position: relative;\n}\n.card_con .card_info .info[data-v-24ebc811] {\n  position: absolute;\n  width: 100%;\n  top: 8%;\n  left: 0;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  padding: 0 4%;\n  color: #fff5d8;\n  font-size: 16px;\n  font-weight: bold;\n  line-height: 20px;\n}\n.card_con .card[data-v-24ebc811] {\n  display: block;\n  -webkit-transition: all 0.3s ease;\n  transition: all 0.3s ease;\n}\n.card_con .bar_code[data-v-24ebc811] {\n  display: block;\n  width: 94%;\n  margin: 5% auto 0;\n  -webkit-transition: all 0.3s ease;\n  transition: all 0.3s ease;\n}\n.card_con .card_num[data-v-24ebc811] {\n  font-size: 16px;\n  line-height: 20px;\n  margin-top: 0px;\n  margin-bottom: 40px;\n  text-align: center;\n  position: relative;\n}\n.card_con .card_num .first[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n}\n.card_con .card_num .none[data-v-24ebc811] {\n  display: none;\n}\n.vip_card .member_benefits[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  padding: 16px 5%;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  border-top: 1px solid #eee;\n}\n.vip_card .member_benefits > div[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  font-size: 14px;\n  line-height: 20px;\n}\n.vip_card .member_benefits > div img[data-v-24ebc811] {\n  width: 16px;\n  display: block;\n  margin-right: 6px;\n}\n.pay_card[data-v-24ebc811] {\n  /*margin-top: 30%;*/\n  width: 100%;\n  -webkit-transition: all 0.3s ease;\n  transition: all 0.3s ease;\n  position: absolute;\n  top: 30%;\n  left: 0;\n}\n.pay_card.card_fix[data-v-24ebc811] {\n  position: fixed;\n  top: 90px;\n  left: 0;\n  z-index: 5;\n  max-width: 720px;\n  -webkit-transition: all 0.3s ease;\n  transition: all 0.3s ease;\n  /*transform: translateY(-112%);*/\n  /*margin-top: 3%;*/\n}\n.member_card.card_scale[data-v-24ebc811] {\n  -webkit-transform: scaleX(0.92) scaleY(0.96);\n          transform: scaleX(0.92) scaleY(0.96);\n  -webkit-transition: all 0.3s ease;\n  transition: all 0.3s ease;\n}\n.member_card.card_scale .card[data-v-24ebc811] {\n  -webkit-transition: all 0.3s ease;\n  transition: all 0.3s ease;\n}\n.card_board[data-v-24ebc811] {\n  position: fixed;\n  top: 0;\n  left: 0;\n  width: 100%;\n  height: 100%;\n  background: -webkit-gradient(linear, left top, left bottom, from(rgba(66, 78, 105, 0.7)), to(rgba(0, 0, 0, 0)));\n  background: linear-gradient(to bottom, rgba(66, 78, 105, 0.7), rgba(0, 0, 0, 0));\n  background-size: 100% auto;\n  z-index: 3;\n  max-width: 720px;\n}\n.pay_card .QR_code[data-v-24ebc811] {\n  width: 130px;\n  height:130px;\n  margin: 0 auto;\n  display: block;\n  /*overflow: hidden;*/\n  /*position: relative;*/\n}\n.set_pass[data-v-24ebc811] {\n  color: #d3d9df;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  margin-top: 4%;\n}\n.set_pass  div[data-v-24ebc811] {\n  background: #394753;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  font-size: 13px;\n  line-height: 30px;\n  padding: 0 15px;\n  border-radius: 20px;\n}\n.set_pass  a[data-v-24ebc811]{\n\n  color: #fff;\n}\n.set_pass  div img[data-v-24ebc811] {\n  width: 6px;\n  margin-left: 5px;\n  display: block;\n  margin-top: 2px;\n}\n.pay_card .member_benefits[data-v-24ebc811] {\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n}\n.pay_card .member_benefits .tips[data-v-24ebc811]{\n  margin-right: 10px;\n}\n.pay_card .member_benefits .iconPar[data-v-24ebc811] {\n  border: 1px solid #D1D1D1;\n  border-radius: 20px;\n  margin-left: 5px;\n  width: 18px;\n  height: 18px;\n  overflow: hidden;\n}\n.pay_card .member_benefits .iconPar img[data-v-24ebc811] {\n  width: 16px;\n  margin-right: 0;\n  position: relative;\n}\n.pay_card .member_benefits .iconPar img.iconColor[data-v-24ebc811] {\n  border-right: 16px solid transparent;\n}\n.account_security[data-v-24ebc811] {\n  /*padding-left: 3%;*/\n  background: #fff;\n}\n.account_security li[data-v-24ebc811] {\n  /*padding-right: 3%;*/\n  padding: 0 3%;\n  border-bottom: 1px solid #eee;\n}\n.account_security li a[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  width: 100%;\n  padding: 10px 0;\n}\n.account_security li .tip[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  font-size: 14px;\n  line-height: 24px;\n  color: #202224;\n}\n.account_security li .tip img[data-v-24ebc811] {\n  width: 18px;\n  display: block;\n  margin-right: 10px;\n}\n.account_security li .more[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  font-size: 13px;\n  line-height: 20px;\n  color: #9ea3a9;\n}\n.account_security li .more img[data-v-24ebc811] {\n  margin-left: 5px;\n  width: 6px;\n}\n.account_list[data-v-24ebc811] {\n  background: #fff;\n  padding-left: 3%;\n  padding-top: 48px;\n}\n.account_list li[data-v-24ebc811] {\n  border-bottom: 1px solid #eee;\n  padding-right: calc(3% + 70px);\n  position: relative;\n}\n.account_list li input[data-v-24ebc811] {\n  display: block;\n  font-size: 14px;\n  line-height: 24px;\n  padding: 10px 0;\n  width: 100%;\n}\n.account_list li .getCode[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 24px;\n  position: absolute;\n  right: 3%;\n  top: 50%;\n  margin-top: -12px;\n}\n.pass_icon[data-v-24ebc811] {\n  content: '';\n  position: absolute;\n  right: 16px;\n  top: 50%;\n  margin-top: -10px;\n  width: 21px;\n}\n.pass_icon img[data-v-24ebc811] {\n  width: 100%;\n  display: block;\n}\n.pass_icon .show_icon[data-v-24ebc811] {\n  display: none;\n}\n.pass_icon.show .show_icon[data-v-24ebc811] {\n  display: block;\n}\n.pass_icon.show .hide_icon[data-v-24ebc811] {\n  display: none;\n}\n.account_tip[data-v-24ebc811] {\n  font-size: 13px;\n  line-height: 20px;\n  margin: 10px auto 0;\n  color: #9ea3a9;\n  width: 94%;\n  text-align: justify;\n}\n.new_board[data-v-24ebc811] {\n  position: fixed;\n  width: 100%;\n  height: 100%;\n  top: 0;\n  left: 0;\n  max-width: 720px;\n  background: rgba(0, 0, 0, 0.6);\n  z-index: 3;\n  display: none;\n}\n.notice_title[data-v-24ebc811] {\n  font-size: 13px;\n  line-height: 24px;\n  padding: 12px 3% 6px;\n  color: #6e7479;\n}\n.notice_det[data-v-24ebc811] {\n  padding-left: 3%;\n  background: #fff;\n}\n.notice_det > div[data-v-24ebc811] {\n  padding-right: calc(3% + 60px);\n  position: relative;\n  padding-top: 15px;\n  padding-bottom: 15px;\n  border-bottom: 1px solid #eee;\n}\n.notice_det > div .pri_address[data-v-24ebc811] {\n  position: absolute;\n  right: 3%;\n  top: 50%;\n  margin-top: -12px;\n}\n.notice_det .tip[data-v-24ebc811] {\n  font-size: 15px;\n  line-height: 20px;\n}\n.notice_det .con[data-v-24ebc811] {\n  font-size: 12px;\n  line-height: 14px;\n  color: #828282;\n  margin-top: 6px;\n}\n.about_us[data-v-24ebc811] {\n  padding: 10% 0;\n}\n.about_us img[data-v-24ebc811] {\n  width: 100px;\n  height: 100px;\n  display: block;\n  margin: 0 auto;\n  border-radius: 10px;\n}\n.about_us div[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 20px;\n  margin-top: 15px;\n  text-align: center;\n}\n.copyright[data-v-24ebc811] {\n  width: 100%;\n  text-align: center;\n  font-size: 12px;\n  line-height: 20px;\n  color: #6e7479;\n  padding-bottom: 15px;\n}\n.ques_type[data-v-24ebc811] {\n  padding: 0 3% 3px;\n}\n.ques_type .title[data-v-24ebc811] {\n  font-size: 14px;\n  color: #8a8a8a;\n  line-height: 40px;\n}\n.ques_type .type_list[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n}\n.ques_type .type_list div[data-v-24ebc811] {\n  width: 31%;\n  border: 1px solid #BABABA;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  font-size: 14px;\n  line-height: 28px;\n  border-radius: 4px;\n  color: #8a8a8a;\n  margin-bottom: 10px;\n}\n.ques_type .type_list div img[data-v-24ebc811] {\n  width: 18px;\n  margin-right: 5px;\n  display: none;\n}\n.ques_type .type_list div.active[data-v-24ebc811] {\n  color: #fff !important;\n}\n.ques_type .type_list div.active img[data-v-24ebc811] {\n  display: block;\n}\n.feedback_con .textArea[data-v-24ebc811] {\n  background: #fff;\n  padding: 10px 3%;\n}\n.feedback_con .textArea textarea[data-v-24ebc811] {\n  display: block;\n  font-size: 13px;\n  line-height: 20px;\n  border: none;\n  text-align: justify;\n  height: 150px;\n  resize: none;\n  padding: 0;\n  width: 100%;\n}\n.refund_title[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 44px;\n  padding: 0 3%;\n  color: #8a8a8a;\n}\n.contact_way[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  padding: 10px 3%;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  background: #fff;\n}\n.contact_way .tip[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 24px;\n  color: #202224;\n}\n.contact_way input[data-v-24ebc811] {\n  text-align: right;\n  font-size: 14px;\n  line-height: 20px;\n  color: #202224;\n}\n.feedback_con .submit[data-v-24ebc811] {\n  display: block;\n  color: #fff;\n  font-size: 16px;\n  line-height: 48px;\n  border-radius: 4px;\n  text-align: center;\n  font-weight: bold;\n  width: 94%;\n  margin: 50px auto 0;\n}\n.edit_btn[data-v-24ebc811] {\n  width: 100%;\n  margin-top: 20px;\n  background: #fff;\n  display: block;\n  color: #202224;\n  font-size: 16px;\n  line-height: 50px;\n}\n.account_head[data-v-24ebc811] {\n  padding: 20px 3%;\n  background: #fff;\n}\n.account_head a[data-v-24ebc811] {\n  position: relative;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  width: 100%;\n}\n.account_head .pic[data-v-24ebc811] {\n  width: 66px;\n  height: 66px;\n  display: block;\n  border-radius: 100%;\n  margin-right: 10px;\n  color: transparent;\n  background-color: transparent;\n}\n.account_head .more[data-v-24ebc811] {\n  position: absolute;\n  width: 6px;\n  right: 0;\n  top: 50%;\n  margin-top: -6px;\n}\n.account_head .nickname[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 20px;\n  color: #202224;\n  overflow: hidden;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  width: 100%!important;\n}\n.account_head .user_name[data-v-24ebc811] {\n  font-size: 12px;\n  line-height: 20px;\n  color: #9ea3a9;\n}\n/*我的会员*/\n.vip_level[data-v-24ebc811] {\n  width: 94%;\n  margin: 15px auto 0;\n  position: relative;\n  -webkit-box-shadow: 0 0 2px 2px #F0E9D8;\n          box-shadow: 0 0 2px 2px #F0E9D8;\n  border-radius: 6px 6px;\n  overflow: hidden;\n}\n.vip_level .vip_bg[data-v-24ebc811] {\n  display: block !important;\n}\n.vip_level .vip_con[data-v-24ebc811] {\n  width: 100%;\n  padding: 0 6% 40px;\n  background: url("+i(t("5rvR"))+") no-repeat;\n  background-size: 102% 100%;\n  position: relative;\n}\n.vip_level .vip_con .member[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  padding: 20px 0 15px;\n}\n.vip_level .vip_con .member .user_head[data-v-24ebc811] {\n  width: 56px;\n  height: 56px;\n  display: block;\n  border-radius: 30px;\n  border: 2px solid #FFFFFF;\n}\n.vip_level .vip_con .member .name[data-v-24ebc811] {\n  color: #84661f;\n  font-size: 14px;\n  line-height: 20px;\n}\n.vip_level .level[data-v-24ebc811] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  position: relative;\n}\n.vip_level .level > span[data-v-24ebc811]{\n  content: '';\n  position: absolute;\n  right:-30px;\n  top:50%;\n  margin-top: -3px;\n  height: 6px;\n  width: 50%;\n  z-index: 1;\n  background: -webkit-gradient(linear,left top, right top,from(#b18c3c),to(#b58f3e));\n  background: linear-gradient(to right,#b18c3c,#b58f3e);\n}\n.vip_level .level .cur[data-v-24ebc811]{\n  color: #eee6c6;\n  background: #795B1D;\n  font-size: 13px;\n  line-height: 14px;\n  padding: 6px 15px;\n  border-radius: 20px;\n  -webkit-box-shadow: 0 1px 2px 2px #b89d64;\n          box-shadow: 0 1px 2px 2px #b89d64;\n  position: relative;\n  z-index: 2;\n}\n.vip_level .level .next[data-v-24ebc811]{\n  background: #dbbe89;\n  width: 30px;\n  line-height: 30px;\n  text-align: center;\n  height: 30px;\n  border-radius: 20px;\n  color: #b58f3e;\n  position: absolute;\n  right: 0;\n  top:50%;\n  margin-top: -15px;\n  z-index: 2;\n}\n.vip_level .vip_con .grow_process[data-v-24ebc811] {\n}\n.vip_level .vip_con .grow_process .cur_level[data-v-24ebc811] {\n  font-size: 12px;\n  line-height: 22px;\n  color: #ab812a;\n}\n.vip_level .vip_con .grow_process .percent[data-v-24ebc811] {\n  width: 100%;\n  height: 4px;\n  background: #AC8336;\n  border-radius: 2px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  position: relative;\n}\n.vip_level .vip_con .grow_process .percent .pro[data-v-24ebc811] {\n  background: #806223;\n  position: absolute;\n  left: 0;\n  height: 100%;\n  top: 0;\n  border-radius: 2px;\n}\n.vip_level .vip_con .grow_process .percent .cur_inter[data-v-24ebc811] {\n  color: #846620;\n  background: #ECD28F;\n  border: 1px solid #D1B26D;\n  position: absolute;\n  top: -30px;\n  right: -8px;\n  font-size: 14px;\n  line-height: 18px;\n  padding: 0 3px;\n  border-radius: 4px;\n}\n.vip_level .vip_con .grow_process .percent .cur_inter[data-v-24ebc811]::before {\n  content: '';\n  position: absolute;\n  height: 0;\n  width: 0;\n  border-left: 5px solid transparent;\n  border-top: 5px solid #D1B26D;\n  border-right: 5px solid transparent;\n  bottom: -5px;\n  left: 50%;\n  margin-left: -5px;\n}\n.vip_level .vip_con .grow_process .percent .cur_inter[data-v-24ebc811]::after {\n  content: '';\n  position: absolute;\n  bottom: -4px;\n  left: 50%;\n  margin-left: -4px;\n  border-left: 4px solid transparent;\n  border-top: 4px solid #ECD28F;\n  border-right: 4px solid transparent;\n}\n.vip_level .vip_con .next_level[data-v-24ebc811] {\n  font-size: 12px;\n  line-height: 22px;\n  color: #ab812a;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n}\n.vip_level .vip_con .bottom_pic[data-v-24ebc811] {\n  position: absolute;\n  width: 100%;\n  height: auto;\n  bottom: 0;\n  z-index: 3;\n  left: 0;\n}\n.vip_level .member_privileges[data-v-24ebc811] {\n  background: #fff;\n  position: relative;\n}\n.vip_level .member_privileges .title[data-v-24ebc811] {\n  font-size: 18px;\n  line-height: 40px;\n  top: -20px;\n  position: absolute;\n  z-index: 5;\n  width: 100%;\n  text-align: center;\n}\n.vip_level .member_privileges ul[data-v-24ebc811] {\n  padding: 30px 20% 15px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n.vip_level .member_privileges ul li img[data-v-24ebc811] {\n  width: 50px;\n  height: 50px;\n  display: block;\n  margin: 0 auto;\n}\n.vip_level .member_privileges ul li div[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 20px;\n  margin-top: 5px;\n  color: #b6966d;\n}\n.member_title[data-v-24ebc811] {\n  width: 100%;\n  display: block;\n  padding: 8px 0;\n}\n.member_new[data-v-24ebc811] {\n  width: 94%;\n  margin: 0 auto;\n  position: relative;\n}\n.member_new > img[data-v-24ebc811] {\n  display: block;\n  height: 100%;\n  width: 100%;\n}\n.member_new .con[data-v-24ebc811] {\n  position: absolute;\n  left: 0;\n  top: 0;\n  width: 100%;\n  height: 100%;\n  z-index: 2;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  padding: 0 15px 5px;\n}\n.member_new .con .level_icon[data-v-24ebc811] {\n  width: 30%;\n  display: block;\n  max-width: 40px;\n}\n.member_new .con .det[data-v-24ebc811] {\n  margin-left: 15px;\n}\n.member_new .con .det .level[data-v-24ebc811] {\n  color: #8b6c26;\n  font-size: 14px;\n  line-height: 24px;\n}\n.vip_level .now[data-v-24ebc811] {\n  color: #8b6c26;\n  font-size: 14px;\n  line-height: 24px;\n  text-align: center;\n  /*margin-top: 10px;*/\n}\n.member_new .con .det .tip[data-v-24ebc811] {\n  font-size: 13px;\n  line-height: 20px;\n  color: #8b6c26;\n  /*text-align: center;*/\n  width: 100%;\n}\n.member_list[data-v-24ebc811] {\n  margin-left: 3%;\n  padding-right: 3%;\n  margin-top: 15px;\n  padding-bottom: 10px;\n}\n.member_list > div[data-v-24ebc811] {\n  /*white-space: nowrap;*/\n  /*overflow: auto;*/\n  /*overflow-y: hidden;*/\n  /*margin-left: 10px;*/\n}\n.member_list > div[data-v-24ebc811]:first-child {\n  margin-left: 0;\n}\n.member_list > div[data-v-24ebc811]::-webkit-scrollbar {\n  opacity: 0;\n}\n.member_list > div .member_new[data-v-24ebc811] {\n  width: 100%;\n  height: 100px;\n  margin: 5px auto;\n}\n.member_list .member_new .con .det[data-v-24ebc811] {\n  /*margin-right: 15px;*/\n  width: 70%;\n  margin-left: 20px;\n}\n/*会员专享价*/\n.member_det[data-v-24ebc811] {\n  padding-bottom: 10px;\n}\n.member_det .pic[data-v-24ebc811] {\n  display: block;\n}\n.member_con[data-v-24ebc811] {\n  width: 94%;\n  margin: -16vw auto 0;\n  background: #fff;\n  position: relative;\n  z-index: 3;\n  padding: 5% 4%;\n  border-radius: 6px;\n}\n.member_con .con li[data-v-24ebc811] {\n  font-size: 14px;\n  line-height: 20px;\n  color: #202224;\n  margin-bottom: 10px;\n}\n.member_con .con li[data-v-24ebc811]:last-child {\n  margin-bottom: 0;\n}\n.member_con .con li span[data-v-24ebc811] {\n  margin-right: 5px;\n}\n.member_con table[data-v-24ebc811] {\n  border: 1px solid #eee;\n  width: 100%;\n  margin-top: 20px;\n}\n.member_con table th[data-v-24ebc811], .member_con table td[data-v-24ebc811] {\n  border: 1px solid #eee;\n  text-align: center;\n  font-size: 14px;\n  line-height: 40px;\n  width: 50%;\n}\n.member_con table th[data-v-24ebc811] {\n  color: #6e7479;\n}\n.member_con table td[data-v-24ebc811] {\n  color: #fe9b1e;\n}\n/*代言*/\n.represent_list .icon[data-v-24ebc811]{\n  width: 36px!important;\n}\n.represent_list li[data-v-24ebc811]{\n  width: 33.3%;\n  position: relative;\n}\n/*.represent_list li::after{*/\n/*width: 1px;*/\n/*height: 30px;*/\n/*background: #eeeeee;*/\n/*content: '';*/\n/*position: absolute;*/\n/*right:0;*/\n/*top:12px*/\n/*}*/\n.represent_list li .text[data-v-24ebc811]{\n  margin-top: 0!important;\n}\n/*新的布局页面*/\n.head_item[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: absolute;\n  left: 5%;\n  top: 50px;\n  width: 90%;\n  height: 100px;\n}\n.head_item>.head[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  width: 70%;\n  height: 100%;\n}\n.head_item>.head>a[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  width: 100%;\n  height: 100%;\n}\n.head_item>.head > a > a[data-v-24ebc811]{\n  display: block;\n  width: 70%;\n}\n.head_item>.head > a > img[data-v-24ebc811]{\n  width: 66px;\n  height:66px;\n  border-radius: 100%;\n}\n.head_item>.head>a .name_vip[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-orient: vertical;\n  -webkit-box-direction: normal;\n      -ms-flex-direction: column;\n          flex-direction: column;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  height: 54%;\n  margin-left: 5%;\n}\n.head_item>.head>a .name_vip>span[data-v-24ebc811]{\n  font-size: 16px;\n  color: #fff;\n}\n.head_item>.head>a .name_vip>img[data-v-24ebc811]{\n  width: 84px;\n  height: 26px;\n  margin-top: 10px;\n}\n.head_item>.vip_stuck[data-v-24ebc811]{\n  width: 86px;\n  height: 48px;\n}\n.head_item>.vip_stuck img[data-v-24ebc811]{\n  width: 100%;\n  height: 100%;\n}\n/*登录*/\n.head_item>.head .name_vip .login[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  height: 100%;\n  color: #fff;\n}\n.head_item>.head .name_vip .login a[data-v-24ebc811]{\n  color: #fff;\n}\n/*钱包*/\n.my_wallet[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: absolute;\n  left: 5%;\n  bottom: 0;\n  width: 90%;\n  height: 44px;\n  background: -webkit-gradient(linear, left top, right top, from(#f8f8f8), to(#efefef));\n  background: linear-gradient(to right, #f8f8f8, #efefef);\n  border-radius: 10px 10px 0 0;\n  -webkit-box-shadow: 0 0 10px 0 rgba(0,0,0,0.1);\n          box-shadow: 0 0 10px 0 rgba(0,0,0,0.1);\n  padding: 0 3%;\n}\n.my_wallet .prompt[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  width: 70%;\n  height: 100%;\n  color: #966155;\n}\n.my_wallet .prompt>div[data-v-24ebc811]{\n  display: block;\n  overflow: hidden;\n  width: 25px;\n  height: 25px;\n}\n.my_wallet .prompt>div>img[data-v-24ebc811]{\n  position: relative;\n  width: 100%;\n  height: 100%;\n  border-right: 1px solid transparent;\n}\n.my_wallet .prompt>span[data-v-24ebc811]{\n  font-size: 14px;\n  margin-left: 10px;\n}\n.my_wallet .enter_into[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  width: 25%;\n  height: 25px;\n  font-size: 14px;\n  border: 1px solid #966155;\n  border-radius: 30px;\n  color: #966155;\n}\n/*订单*/\n.order[data-v-24ebc811]{\n  width: 100%;\n  /*height: 134px;*/\n  background-color: #fff;\n  margin-bottom: 10px;\n  padding-top: 10px;\n  padding-bottom: 20px;\n}\n.order_title[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  padding: 0 20px;\n  height: 50px;\n  margin-bottom: 10px;\n}\n.order_title>.title[data-v-24ebc811]{\n  font-size: 16px;\n  color: #000000;\n  /*font-weight: bold;*/\n}\n.order_title>.order_all[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  font-size: 14px;\n  color: #666;\n}\n.order_title>.order_all[data-v-24ebc811]::after{\n  display: block;\n  content: '';\n  width: 8px;\n  height:8px;\n  border-right: 1px solid #848484;\n  border-bottom: 1px solid #848484;\n  -webkit-transform: rotate(-45deg);\n          transform: rotate(-45deg);\n  margin-left: 6px;\n}\n.order_title>.order_all a[data-v-24ebc811]{\n  color: #848484;\n}\n.order_list[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -ms-flex-pack: distribute;\n      justify-content: space-around;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n  width: 100%;\n}\n.order_list>.list[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-orient: vertical;\n  -webkit-box-direction: normal;\n      -ms-flex-direction: column;\n          flex-direction: column;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  width: 20%;\n  height: 60px;\n}\n.order_list>.list .order_icon[data-v-24ebc811]{\n  position: relative;\n  height: 100%;\n}\n.order_list>.list .order_icon>img[data-v-24ebc811]{\n  width: 36px;\n  height: 36px;\n}\n.order_list>.list .order_icon>.num[data-v-24ebc811]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: absolute;\n  right: 0;\n  top:-2px;\n  border: 1px solid #9e262f;\n  border-radius: 100%;\n  background-color: #fff;\n  color: #9e262f;\n  font-size: 14px;\n  min-width: 20px;\n  min-height: 20px;\n}\n.order_list>.list .order_text[data-v-24ebc811]{\n  font-size: 14px;\n  color: #222;\n}\n\n",""])}});