import { useEffect } from "react";
import { Outlet, useNavigate } from "react-router-dom";
import { ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import { navigationUtil } from "../lib/navigationUtil";
import { toastUtil } from "../lib/toastUtil";

// Gives navigationUtil/toastUtil a way to navigate/show toasts from outside React (e.g.
// httpClient's error handling), which can't call the useNavigate hook themselves.
export function RootLayout() {
  const navigate = useNavigate();

  useEffect(() => {
    navigationUtil.setNavigate(navigate);
    toastUtil.showPendingToast();
  }, [navigate]);

  return (
    <>
      <Outlet />
      <ToastContainer />
    </>
  );
}
