webpackJsonp([117],{"1s+o":function(e,t,n){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var s=n("c2Ch"),i={data:function(){return{business_license:"",other_licence:""}},mounted:function(){this.getData()},methods:{getData:function(){var e=this;Object(s.z)().then(function(t){e.business_license=t.data.business_license,e.other_licence=t.data.other_licence})}}},a={render:function(){var e=this,t=e.$createElement,n=e._self._c||t;return n("div",[n("head-title",{attrs:{title:"证照信息"}}),e._v(" "),n("div",{staticClass:"wary"},[n("div",{staticClass:"business_license"},[n("div",{staticClass:"title"},[e._v("营业执照")]),e._v(" "),n("img",{directives:[{name:"lazy",rawName:"v-lazy",value:e.business_license,expression:"business_license"}],attrs:{alt:""}})]),e._v(" "),e._l(e.other_licence,function(t,s){return n("div",{key:s,staticClass:"business_license"},[n("div",{staticClass:"title"},[e._v(e._s(t.name))]),e._v(" "),n("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.path,expression:"item.path"}],attrs:{alt:""}})])})],2)],1)},staticRenderFns:[]};var c=n("VU/8")(i,a,!1,function(e){n("HuWh")},"data-v-4844fd6c",null);t.default=c.exports},HuWh:function(e,t,n){var s=n("b6qR");"string"==typeof s&&(s=[[e.i,s,""]]),s.locals&&(e.exports=s.locals);n("rjj0")("061acfbc",s,!0,{})},b6qR:function(e,t,n){(e.exports=n("FZ+f")(!1)).push([e.i,"\n.wary[data-v-4844fd6c]{\n  margin-top: 50px;\n}\n.business_license[data-v-4844fd6c]{\n  width: 90%;\n  height: 300px;\n  margin: 0 auto;\n}\n.business_license>.title[data-v-4844fd6c]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  width: 100%;\n  height: 20%;\n  font-size: 16px;\n}\n.business_license>img[data-v-4844fd6c]{\n  width: 100%;\n  height:80%;\n}\n",""])}});