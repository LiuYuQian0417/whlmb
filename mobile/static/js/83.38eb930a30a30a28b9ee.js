webpackJsonp([83],{"2G8n":function(t,n,a){"use strict";Object.defineProperty(n,"__esModule",{value:!0});var i=a("mvHQ"),e=a.n(i),o={name:"SearchOrder",data:function(){return{history:"",key:""}},mounted:function(){localStorage.getItem("search_order")&&(this.history=JSON.parse(localStorage.getItem("search_order")))},methods:{search:function(){if(""!=this.key)if(localStorage.getItem("search_order")){this.history=JSON.parse(localStorage.getItem("search_order"));for(var t=0;t<this.history.length;t++)this.history[t]==this.key&&this.history.splice(t,1);this.history.unshift(this.key),localStorage.setItem("search_order",e()(this.history))}else this.history=[this.key],localStorage.setItem("search_order",e()(this.history));this.$router.push({name:"SearchOrderResult",params:{distribution_type:this.$route.params.distribution_type,keyword:this.key}})},clearHistory:function(){localStorage.removeItem("search_order"),this.history=[]}}},s={render:function(){var t=this,n=t.$createElement,a=t._self._c||n;return a("div",{staticClass:"wrap"},[a("div",{staticClass:"header_cate"},[a("div",{staticClass:"head_search"},[a("div",[a("img",{staticClass:"back",attrs:{src:"/static/img/back.png"},on:{click:function(n){t.$skip.back()}}}),t._v(" "),a("div",{staticClass:"search_text input_text"},[a("img",{staticClass:"icon",attrs:{src:"/static/img/search_icon.png",title:"",alt:""}}),t._v(" "),a("input",{directives:[{name:"model",rawName:"v-model",value:t.key,expression:"key"}],attrs:{type:"text",placeholder:"商品名称/商品编号/订单号",required:"required"},domProps:{value:t.key},on:{input:function(n){n.target.composing||(t.key=n.target.value)}}}),t._v(" "),a("img",{staticClass:"del",attrs:{src:"/static/img/del_icon.png"},on:{click:function(n){t.key=""}}})]),t._v(" "),a("input",{staticClass:"search_btn",attrs:{type:"button",value:"搜订单"},on:{click:t.search}})])])]),t._v(" "),a("div",{staticClass:"search_hot search_history"},[a("div",{staticClass:"title"},[t._v("历史搜索"),a("img",{staticClass:"del_icon",attrs:{src:"/static/img/delete.png"},on:{click:t.clearHistory}})]),t._v(" "),a("ul",{staticClass:"history_list"},t._l(t.history,function(n){return a("li",{on:{click:function(a){t.key=n,t.search()}}},[a("a",[t._v(t._s(n))])])}),0)])])},staticRenderFns:[]};var d=a("VU/8")(o,s,!1,function(t){a("NoD6")},"data-v-d7148c78",null);n.default=d.exports},NoD6:function(t,n,a){var i=a("na+y");"string"==typeof i&&(i=[[t.i,i,""]]),i.locals&&(t.exports=i.locals);a("rjj0")("0fe157f5",i,!0,{})},"na+y":function(t,n,a){(t.exports=a("FZ+f")(!1)).push([t.i,"/*分类*/\n.cate_content[data-v-d7148c78] {\n  position: absolute;\n  width: 100%;\n  max-width: 720px;\n  height: 100%;\n  padding: 48px 0 55px;\n  background-color: #F6F6F6;\n}\n.cate_head[data-v-d7148c78] {\n  padding: 8px 3%;\n  width: 100%;\n  height: 48px;\n  background: #fff;\n}\n.cate_head .head[data-v-d7148c78] {\n  padding-right: 40px;\n  position: relative;\n}\n.cate_head .head .search[data-v-d7148c78] {\n  background: #EDF0F3;\n  height: 32px;\n  display: block;\n  border-radius: 4px;\n  padding: 0 10px;\n  overflow: hidden;\n}\n.cate_head .head .search .icon[data-v-d7148c78] {\n  width: 16px;\n  margin-top: 7px;\n}\n.cate_head .head .search .tip[data-v-d7148c78] {\n  font-size: 13px;\n  line-height: 31px;\n  margin-left: 10px;\n  color: #b3b9c0;\n}\n.cate_head .mes[data-v-d7148c78] {\n  position: absolute;\n  right: 0;\n  top: 0;\n}\n.mes_icon[data-v-d7148c78] {\n  width: 26px;\n  margin-top: 4px;\n  margin-right: 2px;\n}\n.mes_num[data-v-d7148c78] {\n  position: absolute;\n  right: 0;\n  top: 0;\n  color: #fff;\n  font-size: 12px;\n  line-height: 16px;\n  min-width: 16px;\n  padding: 0 3px;\n  border-radius: 10px;\n  text-align: center;\n}\n.cate_con[data-v-d7148c78] {\n  height: 100%;\n  width: 100%;\n  /*padding-bottom: 55px;*/\n  position: relative;\n}\n.cate_list[data-v-d7148c78] {\n  width: 30%;\n  height: 100%;\n  position: absolute;\n  overflow: auto;\n  left: 0;\n  top: 0;\n  overflow: auto;\n  background-color: #f6f6f6;\n}\n.cate_list ul li[data-v-d7148c78] {\n  background: #f6f6f6;\n  border-bottom: 1px solid white;\n  text-align: center;\n  font-size: 14px;\n  line-height: 24px;\n  padding: 14px 8px;\n  position: relative;\n}\n.cate_list ul li .line[data-v-d7148c78] {\n  width: 2px;\n  height: 100%;\n  position: absolute;\n  left: 0;\n  top: 0;\n}\n.cate_list ul li span[data-v-d7148c78] {\n  display: block;\n  color: #202224;\n  overflow: hidden;\n  white-space: nowrap;\n}\n.cate_list_con[data-v-d7148c78] {\n  height: 100%;\n  width: 70%;\n  position: absolute;\n  right: 0;\n  top: 0;\n  overflow: auto;\n  padding: 0 3%;\n}\n.cate_list_con .main_con[data-v-d7148c78] {\n  height: auto;\n}\n.cate_list_con .enter[data-v-d7148c78] {\n  width: 94%;\n  font-size: 15px;\n  line-height: 40px;\n  border-radius: 2px;\n  text-align: center;\n  margin: 15px auto 0;\n}\n.cate_list_con .enter a[data-v-d7148c78] {\n  color: #fff;\n  display: block;\n}\n.cateList[data-v-d7148c78] {\n  border-bottom: 1px solid #eee;\n  padding: 10px 0;\n}\n.cateList[data-v-d7148c78]:last-child {\n  border-bottom: none;\n}\n.cateList .cate_title[data-v-d7148c78] {\n  font-size: 16px;\n  text-align: center;\n  line-height: 36px;\n  color: #202224;\n  position: relative;\n}\n.cateList .cate_title a[data-v-d7148c78] {\n  display: block;\n}\n.cateList .cate_title span[data-v-d7148c78] {\n  position: relative;\n  color: #202224;\n}\n.cateList .cate_title span[data-v-d7148c78]:before {\n  position: absolute;\n  content: '';\n  width: 28px;\n  height: 1px;\n  background: #D8D8D8;\n  left: -40px;\n  top: 50%;\n  margin-top: -0.5px;\n}\n.cateList .cate_title span[data-v-d7148c78]:after {\n  position: absolute;\n  content: '';\n  width: 28px;\n  height: 1px;\n  background: #D8D8D8;\n  right: -40px;\n  top: 50%;\n  margin-top: -0.5px;\n}\n.cateList .cate_title .more[data-v-d7148c78] {\n  width: 16px;\n  position: absolute;\n  right: 0;\n  top: 50%;\n  margin-top: -8px;\n}\n.cateList ul[data-v-d7148c78] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  /*justify-content: space-around;*/\n  overflow: hidden;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n}\n.cateList ul li[data-v-d7148c78] {\n  width: 26%;\n  margin-right: 10%;\n  float: left;\n  margin-top: 10px;\n}\n.cateList ul li[data-v-d7148c78]:nth-child(3n){\n  margin-right: 0;\n}\n.cateList ul li[data-v-d7148c78]:last-child{\n  margin-right: 0;\n}\n.cateList ul li .title[data-v-d7148c78] {\n  font-size: 13px;\n  line-height: 20px;\n  margin-top: 10px;\n  white-space: nowrap;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  color: #202224;\n  text-align: center;\n}\n.cate_main_list ul[data-v-d7148c78] {\n  overflow: hidden;\n  padding: 0 3% 65px;\n  margin: 0 auto;\n  background: #f6f6f6;\n}\n.cate_main_list ul li[data-v-d7148c78] {\n  float: left;\n  width: 49%;\n  margin-left: 2%;\n  margin-top: 3%;\n  border-radius: 2px;\n  background: #fff;\n}\n.cate_main_list ul li[data-v-d7148c78]:nth-child(2n+1) {\n  margin-left: 0;\n  background: #fff;\n}\n.cate_main_list ul li a[data-v-d7148c78] {\n  display: block;\n}\n.cate_main_list ul li .text[data-v-d7148c78] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  padding: 10px;\n}\n.cate_main_list ul li .cate_pic[data-v-d7148c78] {\n  width: 40%;\n}\n.cate_main_list .cate_tit[data-v-d7148c78] {\n  width: 50%;\n  overflow: hidden;\n  white-space: nowrap;\n  font-size: 14px;\n  line-height: 30px;\n  color: #202224;\n}\n.search_cate[data-v-d7148c78] {\n  overflow: hidden;\n  border-top: 1px solid #eee;\n}\n.search_cate li[data-v-d7148c78] {\n  float: left;\n  width: 50%;\n  text-align: center;\n  font-size: 13px;\n  line-height: 40px;\n  color: #202224;\n  position: relative;\n  border-bottom: 1px solid #eee;\n}\n.search_cate li[data-v-d7148c78]:first-child::after {\n  width: 1px;\n  height: 20px;\n  position: absolute;\n  content: '';\n  top: 50%;\n  margin-top: -10px;\n  right: 0;\n  background: #E4E4E4;\n}\n.search_cate li .line[data-v-d7148c78] {\n  width: 100%;\n  height: 2px;\n  position: absolute;\n  left: 0;\n  bottom: 0;\n}\n.search_hot[data-v-d7148c78] {\n  padding-bottom: 15px;\n}\n.search_hot .title[data-v-d7148c78] {\n  font-size: 16px;\n  line-height: 48px;\n  padding: 0 3%;\n  position: relative;\n}\n.search_hot .hot_list[data-v-d7148c78] {\n  white-space: nowrap;\n  font-size: 0;\n  overflow: auto;\n  padding-right: 3%;\n}\n.search_hot .hot_list[data-v-d7148c78]::-webkit-scrollbar {\n  opacity: 0;\n  display: none;\n}\n.search_hot .hot_list li[data-v-d7148c78] {\n  /*display: inline-block;*/\n  /*margin-left: 3%;*/\n  font-size: 13px;\n  line-height: 30px;\n  padding: 0 10px;\n  background: #F3F3F3;\n  border-radius: 10px;\n}\n.search_hot .hot_list li a[data-v-d7148c78], .search_history .history_list li a[data-v-d7148c78] {\n  color: #202224;\n  display: block;\n  word-break: break-all;\n}\n.search_history[data-v-d7148c78] {\n  border-top: 8px solid #f6f6f6;\n}\n.history_list[data-v-d7148c78] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n}\n.history_list li[data-v-d7148c78] {\n  margin-left: 3%;\n  font-size: 13px;\n  line-height: 18px;\n  padding: 6px 10px;\n  background: #F3F3F3;\n  border-radius: 10px;\n  -webkit-box-pack: start;\n      -ms-flex-pack: start;\n          justify-content: flex-start;\n  margin-bottom: 10px;\n  max-width: 94%;\n}\n.del_icon[data-v-d7148c78] {\n  position: absolute;\n  right: 3%;\n  top: 50%;\n  margin-top: -8px;\n  width: 16px;\n}\n.cate_list ul li.active[data-v-d7148c78] {\n  background: white;\n}\n",""])}});