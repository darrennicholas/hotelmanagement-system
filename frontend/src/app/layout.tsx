import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Haven Hotel System",
  description: "Hotel operations management dashboard",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}
