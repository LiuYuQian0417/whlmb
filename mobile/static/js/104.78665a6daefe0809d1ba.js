webpackJsonp([104],{"Gg2+":function(a,e,t){var i=t("Md2r");"string"==typeof i&&(i=[[a.i,i,""]]),i.locals&&(a.exports=i.locals);t("rjj0")("21110caf",i,!0,{})},KvDf:function(a,e,t){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var i=t("DNVT"),n={name:"Preview",mounted:function(){console.log(this.$route.params),new i.a(".swiper-container").slideTo(""==this.$route.params.video?this.$route.params.index:this.$route.params.index+1)}},s={render:function(){var a=this,e=a.$createElement,t=a._self._c||e;return t("div",{staticClass:"wrap",on:{click:function(e){a.$skip.back()}}},[t("div",{staticClass:"swiper-container"},[t("div",{staticClass:"swiper-wrapper"},[""!=a.$route.params.video?t("div",{staticClass:"swiper-slide"},[t("video",{attrs:{src:a.$route.params.video,controls:""}})]):a._e(),a._v(" "),a._l(a.$route.params.imgs,function(a){return t("div",{staticClass:"swiper-slide"},[t("img",{directives:[{name:"lazy",rawName:"v-lazy",value:a,expression:"item"}],key:a})])})],2)])])},staticRenderFns:[]};var r=t("VU/8")(n,s,!1,function(a){t("Gg2+")},"data-v-66fa24ba",null);e.default=r.exports},Md2r:function(a,e,t){(a.exports=t("FZ+f")(!1)).push([a.i,"\n.wrap[data-v-66fa24ba] {\n  background: black;\n  height: 100vh;\n}\n.swiper-container[data-v-66fa24ba] {\n  position: fixed;\n  width: 100vw;\n  height: 100vw;\n  top: 50%;\n  margin-top: -50vw;\n  left: 0;\n}\n.swiper-container img[data-v-66fa24ba], video[data-v-66fa24ba] {\n  width: 100%;\n  height: 100%;\n}\n",""])}});