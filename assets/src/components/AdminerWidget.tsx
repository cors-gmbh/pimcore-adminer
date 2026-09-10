import { CSSProperties, FC, useMemo } from 'react';

const iframeStyle: CSSProperties = {
  border: 'none',
  width: '100%',
  height: '100%',
  backgroundColor: 'var(--color-background, #fff)',
};

const containerStyle: CSSProperties = {
  display: 'flex',
  flexDirection: 'column',
  width: '100%',
  height: '100%',
};

const ADMINER_ROUTE = '/pimcore-studio/api/cors-adminer/adminer';

export const AdminerWidget: FC = () => {
  const src = useMemo(() => ADMINER_ROUTE, []);

  return (
    <div style={ containerStyle }>
      <iframe
        src={ src }
        title="Adminer"
        style={ iframeStyle }
        loading="lazy"
        referrerPolicy="same-origin"
      />
    </div>
  );
};

export default AdminerWidget;
