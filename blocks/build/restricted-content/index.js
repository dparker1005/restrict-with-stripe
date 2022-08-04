(()=>{"use strict";const t=window.wp.blocks,e=window.wp.element,r=window.wp.blockEditor,{Component:s}=wp.element,{__}=wp.i18n,{InspectorControls:c,InnerBlocks:n}=wp.blockEditor,{PanelBody:i,CheckboxControl:o,Spinner:l}=wp.components;class p extends s{constructor(t){super(t),this.state={productList:[],loadingProducts:!0},this.props=t}componentDidMount(){this.fetchProducts()}fetchProducts(){wp.apiFetch({path:"rwstripe/v1/products"}).then((t=>{this.setState({productList:t,loadingProducts:!1})})).catch((t=>{this.setState({productList:t.message,loadingProducts:!1})}))}render(){var t=(0,e.createElement)(l,null);return this.state.loadingProducts||(t=Array.isArray(this.state.productList)?0===this.state.productList.length?(0,e.createElement)("p",null,__("No products found. Please create a product in Stripe.","restrict-with-stripe")):this.state.productList.map((t=>(0,e.createElement)(o,{key:t.id,label:t.name,checked:this.props.rwstripe_restricted_products.includes(t.id),onChange:()=>{let e=[...this.props.rwstripe_restricted_products];e.includes(t.id)?e=e.filter((e=>e!==t.id)):e.push(t.id),this.props.setAttributes({rwstripe_restricted_products:e})}}))):(0,e.createElement)("p",null,__("Could not connect to Stripe. Please check your Stripe connection on the Restrict With Stripe settings page.","restrict-with-stripe"))),(0,e.createElement)("div",null,t)}}function d(){return d=Object.assign?Object.assign.bind():function(t){for(var e=1;e<arguments.length;e++){var r=arguments[e];for(var s in r)Object.prototype.hasOwnProperty.call(r,s)&&(t[s]=r[s])}return t},d.apply(this,arguments)}const{InnerBlocks:a}=wp.blockEditor,u=JSON.parse('{"u2":"rwstripe/restricted-content"}');(0,t.registerBlockType)(u.u2,{edit:function(t){let{attributes:s,setAttributes:o}=t;const l=(0,r.useBlockProps)(),d=l.className.includes("is-selected");return[d&&(0,e.createElement)(c,null,(0,e.createElement)(i,null,(0,e.createElement)("h4",null,__("Select products to restrict by:","restrict-with-stripe")),(0,e.createElement)(p,{rwstripe_restricted_products:s.rwstripe_restricted_products,setAttributes:o}))),d&&(0,e.createElement)("div",l,(0,e.createElement)("span",{className:"rwstripe-block-title"},__("Restricted Content","restrict-with-stripe")),(0,e.createElement)(i,null,(0,e.createElement)("label",null,__("Select products to restrict by:","restrict-with-stripe")),(0,e.createElement)(p,{rwstripe_restricted_products:s.rwstripe_restricted_products,setAttributes:o})),(0,e.createElement)(n,{renderAppender:()=>(0,e.createElement)(n.ButtonBlockAppender,null),templateLock:!1})),!d&&(0,e.createElement)("div",l,(0,e.createElement)("span",{className:"rwstripe-block-title"},__("Restricted Content","restrict-with-stripe")),(0,e.createElement)(n,{renderAppender:()=>(0,e.createElement)(n.ButtonBlockAppender,null),templateLock:!1}))]},save:function(t){let{attributes:s}=t;const c=r.useBlockProps.save();return(0,e.createElement)("div",d({rwstripe_restricted_products:s.rwstripe_restricted_products},c),(0,e.createElement)(a.Content,null))}})})();