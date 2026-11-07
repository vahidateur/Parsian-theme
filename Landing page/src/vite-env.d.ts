/// <reference types="vite/client" />

declare module "*.jpg" {
  const src: string;
  export default src;
}
declare module "*.html?raw" {
  const content: string;
  export default content;
}
