webpackJsonp([21],{"0JBK":function(n,t,a){"use strict";var i={render:function(){var n=this.$createElement,t=this._self._c||n;return t("div",{directives:[{name:"show",rawName:"v-show",value:this.status,expression:"status"}],staticClass:"loading"},[t("div",{attrs:{id:"colorfulPulse"}},[this._m(0),this._v(" "),t("div",{staticStyle:{color:"#333"}},[this._v(this._s(this.text))])])])},staticRenderFns:[function(){var n=this.$createElement,t=this._self._c||n;return t("div",{staticClass:"loadAm"},[t("span",{staticClass:"item-1"}),this._v(" "),t("span",{staticClass:"item-2"}),this._v(" "),t("span",{staticClass:"item-3"}),this._v(" "),t("span",{staticClass:"item-4"})])}]};var e=a("VU/8")({data:function(){return{text:"正在加载中...",status:!1}},methods:{show:function(n){this.text=n,this.status=!0},close:function(){this.status=!1}}},i,!1,function(n){a("AM2o")},"data-v-19306d27",null);t.a=e.exports},"3GDu":function(n,t,a){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var i,e,o,s=a("mvHQ"),l=a.n(s),r=a("+aT9"),d=a.n(r),c=a("Jnm5"),m=a("mtWM"),p=a.n(m),f=a("0JBK"),u={name:"Map",components:{HeadSearch:c.a,loading:f.a},data:function(){return{show:!1,location_name:"",location_address:"",list:[],location:""}},mounted:function(){var n=this,t=this;i=new d.a.Map("container",{resizeEnable:!0,zoom:16}),console.log(this.Configuration.autonavi_key),""!=this.$route.params.address&&p()({url:"https://restapi.amap.com/v3/geocode/geo",method:"GET",params:{address:this.$route.params.address,city:this.$route.params.city,output:"JSON",key:this.Configuration.autonavi_key}}).then(function(t){console.log(t),n.location=t.data.geocodes[0].location}),i.plugin("AMap.Geolocation",function(){n.$refs.loading.show("正在定位..."),(e=new d.a.Geolocation({enableHighAccuracy:!0,timeout:1e4,buttonOffset:new d.a.Pixel(10,20),zoomToAccuracy:!0,buttonPosition:"RB"})).getCurrentPosition(),""==t.$route.params.address?(d.a.event.addListener(e,"complete",function(n){t.$refs.loading.close(),console.log(n);var a=new d.a.LngLat(n.position.lng,n.position.lat);i.setCenter(a),t.drawBubble(a)}),d.a.event.addListener(e,"error",function(n){t.$refs.loading.close(),t.$skip.back(),t.$Toast.toast("请开启定位权限"),console.log(n)})):p()({url:"https://restapi.amap.com/v3/geocode/geo",method:"GET",params:{address:t.$route.params.address,city:t.$route.params.city,output:"JSON",key:t.Configuration.autonavi_key}}).then(function(n){t.$refs.loading.close(),console.log(n);var a=n.data.geocodes[0].location.split(","),e=a[0],o=a[1],s=new d.a.LngLat(e,o);i.setCenter(s),t.drawBubble(s)})}),i.on("touchend",function(n){var a=new d.a.LngLat(i.getCenter().lng,i.getCenter().lat);t.drawBubble(a)})},methods:{getCurrentLocation:function(){e.getCurrentPosition()},drawBubble:function(n){var t=this;d.a.service(["AMap.PlaceSearch"],function(){o=new d.a.PlaceSearch({pageSize:20,pageIndex:1});var a=[n.lng,n.lat];o.searchNearBy("",a,200,function(n,a){console.log(a),"OK"===a.info&&(t.show=!0,t.location_name=a.poiList.pois[0].name,t.location_address=a.poiList.pois[0].address,t.list=a.poiList.pois,t.$nextTick(function(){t.commonJs.changeColor()}))})})},search:function(n){var t=this;0!=n.length&&(console.log(n),o.search(n,function(n,a){console.log(a);var e=new d.a.LngLat(a.poiList.pois[0].location.lng,a.poiList.pois[0].location.lat);i.setCenter(e),t.drawBubble(e)}))},chooseAddress:function(n){console.log(n),this.$bus.$emit("chooseAddress",n),sessionStorage.setItem("chooseAddress",l()(n)),this.$skip.back()}}},g={render:function(){var n=this,t=n.$createElement,a=n._self._c||t;return a("div",[a("head-search",{attrs:{placeholder:"搜索写字楼、小区"},on:{search:n.search}}),n._v(" "),a("loading",{ref:"loading"}),n._v(" "),a("div",{attrs:{id:"container"}},[a("img",{attrs:{src:"/static/img/gwc-sl-dh.png"},on:{click:n.getCurrentLocation}}),n._v(" "),n.show?a("div",{staticClass:"container_div"},[a("div",{staticClass:"map_center_point"},[a("div",{staticClass:"map_location_info"},[a("div",{staticClass:"map_location_desc"},[n._v(n._s(n.location_name))]),n._v(" "),a("div",{staticClass:"map_location_name"},[n._v(n._s(n.location_address))])]),n._v(" "),a("div",{staticClass:"map_location_submit bgActive",on:{click:function(t){n.chooseAddress(n.list[0])}}},[n._v("确认")])]),n._v(" "),n._m(0)]):n._e()]),n._v(" "),a("ul",{staticClass:"map_poi_list"},n._l(n.list,function(t,i){return a("li",{on:{click:function(a){n.chooseAddress(t)}}},[a("div",{staticClass:"iconPar",staticStyle:{"margin-left":"10px",width:"20px",overflow:"hidden"}},[a("img",0==i?{staticClass:"iconColor",attrs:{src:"/static/img/loc4.png"}}:{attrs:{src:"/static/img/loc4.png"}})]),n._v(" "),a("div",{staticClass:"map_list_info",class:{textActive:0==i}},[a("div",{staticClass:"name"},[n._v(n._s(t.name))]),n._v(" "),a("div",{staticClass:"address"},[n._v(n._s(t.address))])])])}),0)],1)},staticRenderFns:[function(){var n=this.$createElement,t=this._self._c||n;return t("div",{staticClass:"iconPar",staticStyle:{width:"30px",margin:"10px auto 0",overflow:"hidden"}},[t("img",{staticStyle:{width:"30px",height:"30px","border-right":"30px solid transparent",position:"relative"},attrs:{src:"/static/img/gwc-sl-dw.png"}})])}]};var h=a("VU/8")(u,g,!1,function(n){a("ce5X")},null,null);t.default=h.exports},AM2o:function(n,t,a){var i=a("wGMu");"string"==typeof i&&(i=[[n.i,i,""]]),i.locals&&(n.exports=i.locals);a("rjj0")("3f29deba",i,!0,{})},ce5X:function(n,t,a){var i=a("qOnS");"string"==typeof i&&(i=[[n.i,i,""]]),i.locals&&(n.exports=i.locals);a("rjj0")("d5de5a18",i,!0,{})},qOnS:function(n,t,a){(n.exports=a("FZ+f")(!1)).push([n.i,"\n.amap-logo, .amap-copyright {\n  display: none !important;\n}\n#container {\n  width: 100%;\n  height: 280px;\n  position: relative;\n}\n#container > img {\n  width: 32px;\n  height: 32px;\n  position: absolute;\n  left: 10px;\n  bottom: 10px;\n  z-index: 100;\n}\n.map_center_point {\n  width: 268px;\n  height: 55px;\n  background: rgba(0, 0, 0, 0.6);\n  position: relative;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  border-radius: 5px;\n}\n#container .container_div {\n  position: absolute;\n  z-index: 1000;\n  top: 50%;\n  left: 50%;\n  margin-left: -134px;\n  margin-top: -84px;\n}\n.map_center_point::after {\n  content: '';\n  position: absolute;\n  bottom: -8px;\n  left: 50%;\n  margin-left: -8px;\n  border-left: 8px solid transparent;\n  border-top: 8px solid rgba(0, 0, 0, 0.7);\n  border-right: 8px solid transparent;\n}\n.map_location_info {\n  width: 172px;\n  margin-left: 10px;\n}\n.map_location_desc {\n  font-size: 15px;\n  line-height: 20px;\n  color: white;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  white-space: nowrap;\n}\n.map_location_name {\n  font-size: 14px;\n  line-height: 20px;\n  color: white;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  white-space: nowrap;\n}\n.map_location_submit {\n  width: 60px;\n  font-size: 14px;\n  line-height: 30px;\n  text-align: center;\n  border-radius: 3px;\n  color: white;\n  margin-left: 10px;\n}\n.map_poi_list {\n  width: 100%;\n  height: calc(100vh - 328px);\n  overflow: auto;\n}\n.map_poi_list img {\n  width: 20px !important;\n  height: 20px;\n  border-right: 20px solid transparent;\n  position: relative;\n}\n.map_poi_list li {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  height: 58px;\n  border-bottom: 1px solid #f6f6f6;\n}\n.map_list_info {\n  margin-left: 10px;\n  width: calc(100% - 52px);\n}\n.map_list_info .name {\n  font-size: 14px;\n  line-height: 1;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  white-space: nowrap;\n}\n.map_list_info .address {\n  font-size: 13px;\n  line-height: 1;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  white-space: nowrap;\n  margin-top: 10px;\n}\n",""])},wGMu:function(n,t,a){(n.exports=a("FZ+f")(!1)).push([n.i,"\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n/* 效果CSS开始 */\n.loading[data-v-19306d27]{\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  position: fixed;\n  left: 0;\n  top: 0;\n  z-index: 999999;\n  width: 100%;\n  height: 100%;\n  /*background-color: rgba(0,0,0,0.3);*/\n}\n#colorfulPulse[data-v-19306d27] {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-orient: vertical;\n  -webkit-box-direction: normal;\n      -ms-flex-direction: column;\n          flex-direction: column;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  width: 150px;\n  height: 50px;\n}\n#colorfulPulse span[data-v-19306d27] {\n  display: inline-block;\n  width: 5px;\n  height: 30px;\n  animation-name: scale-data-v-19306d27;\n  -webkit-animation-name: scale-data-v-19306d27;\n  -moz-animation-name: scale-data-v-19306d27;\n  -ms-animation-name: scale-data-v-19306d27;\n  -o-animation-name: scale-data-v-19306d27;\n  animation-duration: 1.2s;\n  -webkit-animation-duration: 1.2s;\n  -moz-animation-duration: 1.2s;\n  -ms-animation-duration: 1.2s;\n  -o-animation-duration: 1.2s;\n  animation-iteration-count: infinite;\n  -webkit-animation-iteration-count: infinite;\n  -moz-animation-iteration-count: infinite;\n  -ms-animation-iteration-count: infinite;\n  -o-animation-iteration-count: infinite;\n  border-radius: 20px;\n}\nspan.item-1[data-v-19306d27] {\n  background: -webkit-gradient(linear,left top, left bottom,from(#d4aeff),to(#667cfa));\n  background: linear-gradient(#d4aeff,#667cfa);\n}\nspan.item-2[data-v-19306d27] {\n  background: -webkit-gradient(linear,left top, left bottom,from(#ffbe32),to(#ff6d62));\n  background: linear-gradient(#ffbe32,#ff6d62)\n}\nspan.item-3[data-v-19306d27] {\n  background: -webkit-gradient(linear,left top, left bottom,from(#ff8ec6),to(#d36aff));\n  background: linear-gradient(#ff8ec6,#d36aff);\n}\nspan.item-4[data-v-19306d27] {\n  background: -webkit-gradient(linear,left top, left bottom,from(#23d7d1),to(#23d777));\n  background: linear-gradient(#23d7d1,#23d777);\n}\nspan.item-5[data-v-19306d27] {\n  background: #c0392b;\n}\nspan.item-6[data-v-19306d27] {\n  background: #e74c3c;\n}\nspan.item-7[data-v-19306d27] {\n  background: #e74c8c;\n}\n.item-1[data-v-19306d27] {\n  animation-delay: -1s;\n  -webkit-animation-delay: -1s;\n  -moz-animation-delay: -1s;\n  -ms-animation-delay: -1s;\n  -o-animation-delay: -1s;\n}\n.item-2[data-v-19306d27] {\n  animation-delay: -0.9s;\n  -webkit-animation-delay: -0.9s;\n  -moz-animation-delay: -0.9s;\n  -ms-animation-delay: -0.9s;\n  -o-animation-delay: -0.9s;\n}\n.item-3[data-v-19306d27] {\n  animation-delay: -0.8s;\n  -webkit-animation-delay: -0.8s;\n  -moz-animation-delay: -0.8s;\n  -ms-animation-delay: -0.8s;\n  -o-animation-delay: -0.8s;\n}\n.item-4[data-v-19306d27] {\n  animation-delay: -0.7s;\n  -webkit-animation-delay: -0.7s;\n  -moz-animation-delay: -0.7s;\n  -ms-animation-delay: -0.7s;\n  -o-animation-delay: -0.7s;\n}\n.item-5[data-v-19306d27] {\n  animation-delay: -0.6s;\n  -webkit-animation-delay: -0.6s;\n  -moz-animation-delay: -0.6s;\n  -ms-animation-delay: -0.6s;\n  -o-animation-delay: -0.6s;\n}\n.item-6[data-v-19306d27] {\n  animation-delay: -0.5s;\n  -webkit-animation-delay: -0.5s;\n  -moz-animation-delay: -0.5s;\n  -ms-animation-delay: -0.5s;\n  -o-animation-delay: -0.5s;\n}\n.item-7[data-v-19306d27] {\n  animation-delay: -0.4s;\n  -webkit-animation-delay: -0.4s;\n  -moz-animation-delay: -0.4s;\n  -ms-animation-delay: -0.4s;\n  -o-animation-delay: -0.4s;\n}\n@-webkit-keyframes scale-data-v-19306d27 {\n0%, 40%, 100% {\n    -webkit-transform: scaleY(0.2);\n    transform: scaleY(0.2);\n}\n20%, 60% {\n    -webkit-transform: scaleY(1);\n    transform: scaleY(1);\n}\n}\n@keyframes scale-data-v-19306d27 {\n0%, 40%, 100% {\n    -webkit-transform: scaleY(0.2);\n    transform: scaleY(0.2);\n}\n20%, 60% {\n    -webkit-transform: scaleY(1);\n    transform: scaleY(1);\n}\n}\n",""])}});