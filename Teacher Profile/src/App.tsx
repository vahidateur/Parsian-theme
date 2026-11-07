import previewDocument from '../preview.html?raw'

export default function App() {
  return (
    <iframe
      className="site-preview"
      srcDoc={previewDocument}
      title="پروفایل استاد دکتر آرش کامکار"
    />
  )
}
