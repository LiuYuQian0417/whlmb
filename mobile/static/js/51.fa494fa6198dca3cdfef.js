webpackJsonp([51],{"+G8d":function(e,t,i){(e.exports=i("FZ+f")(!1)).push([e.i,"\n.areaCon[data-v-229e9880]{\n  position: absolute;\n  width: 100%;\n  height: calc(100% - 48px);\n  top:48px;\n  left: 0;\n  max-width: 720px;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  background: #f6f6f6;\n}\n.province[data-v-229e9880]{\n  height: 100%;\n  overflow: auto;\n  width: 24%;\n  background: #efefef;\n}\n.city[data-v-229e9880]{\n  height: 100%;\n  overflow: auto;\n  width: 38%;\n  background: #f7f7f7;\n}\n.area[data-v-229e9880]{\n  height: 100%;\n  overflow: auto;\n  width: 38%;\n  background: #fff;\n}\n.province li[data-v-229e9880]{\n  height: 56px;\n  font-size: 14px;\n  line-height: 20px;\n  color: #202224;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: relative;\n}\n.province li div[data-v-229e9880]{\n  position: relative;\n  padding: 0 10px;\n  border-left: 3px solid transparent;\n}\n.province li.active[data-v-229e9880]{\n  background: #f4f4f4;\n}\n.province li.active div[data-v-229e9880]{\n  /*border-left: 3px solid #f23030;*/\n  /*color: #f23030;*/\n  border-left: 3px solid transparent;\n  color: #202224;\n}\n.city li[data-v-229e9880]{\n  height: 56px;\n  padding: 0 10px;\n  font-size: 14px;\n  line-height: 20px;\n  color: #202224;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: relative;\n}\n.city li.active[data-v-229e9880]{\n  /*color: #f23030;*/\n  color: #202224;\n  background: #f9f9f9;\n}\n.area li[data-v-229e9880]{\n  height: 56px;\n  padding: 0 10px;\n  font-size: 14px;\n  line-height: 20px;\n  color: #202224;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  position: relative;\n}\n.area li .text[data-v-229e9880]{\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n.area li.active[data-v-229e9880]{\n  /*color: #f23030;*/\n  color: #202224;\n}\n.area li .iconPar[data-v-229e9880]{\n  display: none;\n  width: 16px;\n  overflow: hidden;\n}\n.area li .iconColor[data-v-229e9880]{\n  position: relative;\n  border-right: 16px solid transparent;\n}\n.area li.active .iconPar[data-v-229e9880]{\n  display: block;\n}\n\n\n",""])},"37vn":function(e,t,i){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var n=i("5Edq"),a={render:function(){var e=this,t=e.$createElement,i=e._self._c||t;return i("div",{staticClass:"areaCon"},[i("head-title",{attrs:{title:"所在地区"}}),e._v(" "),i("ul",{staticClass:"province"},e._l(e.provinceList,function(t,n){return i("li",{class:{active:e.provinceIndex==n},on:{click:function(i){e.province(t,n)}}},[i("div",{class:{colorActive:e.provinceIndex==n}},[e._v(e._s(t.area_name))])])}),0),e._v(" "),i("ul",{directives:[{name:"show",rawName:"v-show",value:e.showCity,expression:"showCity"}],staticClass:"city"},e._l(e.cityList,function(t,n){return i("li",{class:{"active textActive":e.cityIndex==n},on:{click:function(i){e.city(t,n)}}},[e._v(e._s(t.area_name))])}),0),e._v(" "),i("ul",{directives:[{name:"show",rawName:"v-show",value:e.showArea,expression:"showArea"}],staticClass:"area"},e._l(e.areaList,function(t,n){return i("li",{class:{active:e.areaIndex==n},on:{click:function(i){e.area(t,n)}}},[i("div",{staticClass:"text",class:{textActive:e.areaIndex==n}},[e._v(e._s(t.area_name))]),e._v(" "),e._m(0,!0)])}),0)],1)},staticRenderFns:[function(){var e=this.$createElement,t=this._self._c||e;return t("div",{staticClass:"iconPar"},[t("img",{staticClass:"iconColor",attrs:{src:"/static/img/active.png",alt:""}})])}]};var o=function(e){i("o3mb")},r=i("VU/8")(n.a,a,!1,o,"data-v-229e9880",null);t.default=r.exports},"5Edq":function(e,t,i){"use strict";(function(e){var n=i("dyt9"),a=i("c2Ch");t.a={name:"MerchantArea",components:{HeadTitle:n.a},data:function(){return{provinceList:[],provinceIndex:-1,cityList:[],cityIndex:-1,areaList:[],areaIndex:-1,showCity:!0,showArea:!0,provinceName:"",cityName:"",areaName:"",allName:"",province_id:0,city_id:""}},mounted:function(){this.getCityList(),this.commonJs.changeColor(),console.log(this.$route.query.province_id),""!=this.$route.query.province_id&&(this.provinceName=this.$route.query.chooseProvince,this.cityName=this.$route.query.chooseCity,this.areaName=this.$route.query.chooseArea,this.province_id=this.$route.query.province_id,this.city_id=this.$route.query.city_id,this.provinceIndex=this.$route.query.provinceIndex,this.cityIndex=this.$route.query.cityIndex,this.areaIndex=this.$route.query.areaIndex,this.provinceData(),this.cityData())},methods:{getCityList:function(){var e=this;Object(a.F)({parent_id:0}).then(function(t){e.$nextTick(function(){e.provinceList=t.result,e.commonJs.changeColor()})})},provinceData:function(){var e=this;Object(a.F)({parent_id:this.province_id}).then(function(t){e.$nextTick(function(){e.cityList=t.result,console.log(t),e.showCity=!0,e.showArea=!0,e.commonJs.changeColor()})})},cityData:function(){var e=this;Object(a.F)({parent_id:this.city_id}).then(function(t){e.$nextTick(function(){e.areaList=t.result,e.commonJs.changeColor()}),e.showArea=!0})},province:function(t,i){this.provinceName=t.area_name,this.provinceIndex=i,this.cityIndex=-1,this.areaIndex=-1,this.province_id=t.area_id,this.provinceData(),e(".province li div").removeAttr("style"),e(".city li").removeAttr("style"),e(".area li .text").removeAttr("style")},city:function(t,i){this.cityName=t.area_name,this.cityIndex=i,this.areaIndex=-1,this.city_id=t.area_id,e(".city li").removeAttr("style"),e(".area li .text").removeAttr("style"),this.cityData()},area:function(t,i){this.areaName=t.area_name,e(".area li .text").removeAttr("style"),this.areaIndex=i,this.commonJs.changeColor(),this.$bus.$emit("area",this.provinceName,this.cityName,this.areaName,this.province_id,this.city_id,this.provinceIndex,this.cityIndex,this.areaIndex),this.$skip.back()}}}}).call(t,i("7t+N"))},o3mb:function(e,t,i){var n=i("+G8d");"string"==typeof n&&(n=[[e.i,n,""]]),n.locals&&(e.exports=n.locals);i("rjj0")("0bc79648",n,!0,{})}});