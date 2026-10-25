import Cocoa
import FlutterMacOS

class MainFlutterWindow: NSWindow {
  override func awakeFromNib() {
    let flutterViewController = FlutterViewController()
    var windowFrame = self.frame
    self.contentViewController = flutterViewController

    // Tamaño mínimo (mismo valor que minWindowSize en lib/core/window.dart y win32_window.h)
    // y tamaño inicial cómodo para las tablas de la operación
    let minSize = NSSize(width: 1100, height: 700)
    self.contentMinSize = minSize
    if windowFrame.width < 1360 || windowFrame.height < 860 {
      windowFrame.size = NSSize(width: 1360, height: 860)
    }
    self.setFrame(windowFrame, display: true)
    self.center()

    RegisterGeneratedPlugins(registry: flutterViewController)

    super.awakeFromNib()
  }
}
