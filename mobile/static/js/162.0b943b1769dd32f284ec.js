webpackJsonp([162],{"0ZKu":function(t,n,e){"use strict";Object.defineProperty(n,"__esModule",{value:!0});var s=e("dyt9"),o=e("c2Ch"),a={name:"MerchantClassifyTips",components:{HeadTitle:s.a},data:function(){return{content:""}},mounted:function(){this.getData(),this.commonJs.changeColor()},methods:{getData:function(){var t=this;Object(o.N)({article_id:35}).then(function(n){t.content=n.content,console.log(n)})}}},i={render:function(){var t=this.$createElement,n=this._self._c||t;return n("div",{staticClass:"useCon"},[n("head-title",{attrs:{title:"查看详情"}}),this._v(" "),n("div",{staticClass:"description_con",domProps:{innerHTML:this._s(this.content)}})],1)},staticRenderFns:[]};var c=e("VU/8")(a,i,!1,function(t){e("5w2n")},"data-v-03661b6a",null);n.default=c.exports},"5w2n":function(t,n,e){var s=e("QNg0");"string"==typeof s&&(s=[[t.i,s,""]]),s.locals&&(t.exports=s.locals);e("rjj0")("53e66442",s,!0,{})},QNg0:function(t,n,e){(t.exports=e("FZ+f")(!1)).push([t.i,"\n.useCon[data-v-03661b6a]{\n  margin-top: 48px;\n}\n",""])}});