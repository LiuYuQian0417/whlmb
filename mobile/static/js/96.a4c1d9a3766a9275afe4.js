webpackJsonp([96],{"+F9W":function(t,i,n){"use strict";Object.defineProperty(i,"__esModule",{value:!0});var a=n("c2Ch"),s={name:"City",data:function(){return{location_city:"",hotList:[],allList:[]}},mounted:function(){this.getData()},methods:{getData:function(){var t=this;Object(a.L)().then(function(i){t.$nextTick(function(){t.hotList=i.hot,t.allList=i.result,t.location_city=t.Global.location_city})})},locId:function(t){return t.initials},locHref:function(t){return"#"+t.initials},chooseCity:function(t){sessionStorage.setItem("city",t),this.Global.current_city=t,this.$store.commit("changeCity",t),this.$skip.back()}}},e={render:function(){var t=this,i=t.$createElement,n=t._self._c||i;return n("div",{staticClass:"CityCon"},[n("head-title",{attrs:{title:"城市选择"}}),t._v(" "),n("div",{staticClass:"cur_city"},[t._m(0),t._v(" "),n("div",{staticClass:"city_list",on:{click:function(i){t.chooseCity(t.location_city)}}},[n("div",[t._v(t._s(t.location_city))])])]),t._v(" "),n("div",{staticClass:"all"},[t._m(1),t._v(" "),n("div",{staticClass:"city_list"},[n("div",{on:{click:function(i){t.chooseCity("全国")}}},[t._v("全国")])])]),t._v(" "),n("div",{staticClass:"all"},[t._m(2),t._v(" "),n("div",{staticClass:"hot_list"},t._l(t.hotList,function(i){return n("div",{staticClass:"list",on:{click:function(n){t.chooseCity(i.area_name)}}},[n("div",[t._v(t._s(i.area_name))])])}),0)]),t._v(" "),n("div",{staticClass:"list_city"},t._l(t.allList,function(i){return n("div",[n("div",{staticClass:"cur_tips",attrs:{id:t.locId(i)}},[t._v(t._s(i.initials))]),t._v(" "),n("div",{staticClass:"cityList"},t._l(i.list,function(i){return n("div",{staticClass:"city_list",on:{click:function(n){t.chooseCity(i.area_name)}}},[n("div",[t._v(t._s(i.area_name))])])}),0)])}),0),t._v(" "),void 0!=t.allList&&0!=t.allList.length?n("div",{staticClass:"word_fix"},[n("ul",t._l(t.allList,function(i){return n("li",[n("a",{style:"color:"+t.commonJs.changeColor().colors,attrs:{href:t.locHref(i)}},[t._v(t._s(i.initials))])])}),0)]):t._e()],1)},staticRenderFns:[function(){var t=this.$createElement,i=this._self._c||t;return i("div",{staticClass:"cur_tips"},[i("img",{attrs:{src:"/static/img/loc4.png"}}),this._v("当前定位城市")])},function(){var t=this.$createElement,i=this._self._c||t;return i("div",{staticClass:"cur_tips"},[i("img",{attrs:{src:"/static/img/bx.png"}}),this._v("不限城市")])},function(){var t=this.$createElement,i=this._self._c||t;return i("div",{staticClass:"cur_tips"},[i("img",{attrs:{src:"/static/img/rm.png"}}),this._v("热门城市")])}]};var l=n("VU/8")(s,e,!1,function(t){n("6DJC")},"data-v-7591a668",null);i.default=l.exports},"6DJC":function(t,i,n){var a=n("DhRx");"string"==typeof a&&(a=[[t.i,a,""]]),a.locals&&(t.exports=a.locals);n("rjj0")("057ccdbe",a,!0,{})},DhRx:function(t,i,n){(t.exports=n("FZ+f")(!1)).push([t.i,"\n.cur_tips[data-v-7591a668]{\n  padding: 58px 3% 10px;\n  margin-top: -48px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  font-size: 14px;\n  line-height: 20px;\n}\n.cur_tips img[data-v-7591a668]{\n  width: 20px;\n  margin-right: 5px;\n}\n.city_list[data-v-7591a668]{\n  padding-left: 3%;\n  background: #fff;\n}\n.city_list div[data-v-7591a668]{\n  border-bottom: 1px solid #eee;\n  font-size: 14px;\n  line-height: 24px;\n  padding: 10px 16px 9px 0;\n  color: #202224;\n}\n.cur_city .city_list div[data-v-7591a668]{\n  font-weight: bold;\n}\n.word_fix[data-v-7591a668]{\n  position: fixed;\n  width: 14px;\n  height: 100%;\n  top:0;\n  right:0;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n}\n.word_fix ul[data-v-7591a668]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n}\n.word_fix li[data-v-7591a668]{\n  font-size: 14px;\n  line-height: 17px;\n  padding: 2px 0;\n  color: #202224;\n  text-align: center;\n}\n.hot_list[data-v-7591a668]{\n  background: #fff;\n  overflow: hidden;\n  padding-bottom: 8px;\n}\n.hot_list .list[data-v-7591a668]{\n  width: 22%;\n  margin-left: 2.4%;\n  background: #f6f6f6;\n  color: #202224;\n  border-radius: 2px;\n  float: left;\n  font-size: 14px;\n  line-height: 32px;\n  text-align: center;\n  margin-top: 8px;\n  white-space: nowrap;\n  text-overflow: ellipsis;\n  overflow: hidden;\n}\n.CityCon[data-v-7591a668] {\n  padding-top: 48px;\n  height: 100vh;\n  overflow: auto;\n  background: #f6f6f6;\n}\n\n",""])}});