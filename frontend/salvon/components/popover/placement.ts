export const placementConfiguration = {
  //------------ left section

  'left-top': {
    anchorOrigin: {
      vertical: 'top',
      horizontal: 'left',
    },
    transformOrigin: {
      vertical: 'bottom',
      horizontal: 'right',
    },
  },
  'left-center': {
    anchorOrigin: {
      vertical: 'center',
      horizontal: 'left',
    },
    transformOrigin: {
      vertical: 'center',
      horizontal: 'right',
    },
  },
  'left-bottom': {
    anchorOrigin: {
      vertical: 'bottom',
      horizontal: 'left',
    },
    transformOrigin: {
      vertical: 'top',
      horizontal: 'right',
    },
  },

  //------------ top section

  'top-left': {
    anchorOrigin: {
      vertical: 'top',
      horizontal: 'left',
    },
    transformOrigin: {
      vertical: 'bottom',
      horizontal: 'left',
    },
  },
  'top-center': {
    anchorOrigin: {
      vertical: 'top',
      horizontal: 'center',
    },
    transformOrigin: {
      vertical: 'bottom',
      horizontal: 'center',
    },
  },
  'top-right': {
    anchorOrigin: {
      vertical: 'top',
      horizontal: 'right',
    },
    transformOrigin: {
      vertical: 'bottom',
      horizontal: 'right',
    },
  },

  //------------ right section

  'right-top': {
    anchorOrigin: {
      vertical: 'top',
      horizontal: 'right',
    },
    transformOrigin: {
      vertical: 'bottom',
      horizontal: 'left',
    },
  },
  'right-center': {
    anchorOrigin: {
      vertical: 'center',
      horizontal: 'right',
    },
    transformOrigin: {
      vertical: 'center',
      horizontal: 'left',
    },
  },
  'right-bottom': {
    anchorOrigin: {
      vertical: 'bottom',
      horizontal: 'right',
    },
    transformOrigin: {
      vertical: 'top',
      horizontal: 'left',
    },
  },

  //------------ bottom section

  'bottom-left': {
    anchorOrigin: {
      vertical: 'bottom',
      horizontal: 'left',
    },
    transformOrigin: {
      vertical: 'top',
      horizontal: 'left',
    },
  },
  'bottom-center': {
    anchorOrigin: {
      vertical: 'bottom',
      horizontal: 'center',
    },
    transformOrigin: {
      vertical: 'top',
      horizontal: 'center',
    },
  },
  'bottom-right': {
    anchorOrigin: {
      vertical: 'bottom',
      horizontal: 'right',
    },
    transformOrigin: {
      vertical: 'top',
      horizontal: 'right',
    },
  },
};

export type Placement = keyof typeof placementConfiguration;
